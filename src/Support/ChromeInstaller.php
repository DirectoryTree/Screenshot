<?php

namespace DirectoryTree\Screenshot\Support;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class ChromeInstaller
{
    /**
     * The base URL for the official Chrome for Testing release manifests.
     *
     * @var string
     */
    protected const MANIFEST_URL = 'https://googlechromelabs.github.io/chrome-for-testing/';

    /**
     * Create a Chrome for Testing installer.
     */
    public function __construct(
        protected string $directory,
        protected ClientInterface $http = new Client,
    ) {}

    /**
     * Resolve the selected installation without making a network request.
     */
    public function path(): ?string
    {
        $manifest = $this->directory.'/installed.json';

        if (! is_file($manifest)) {
            return null;
        }

        $installed = json_decode(file_get_contents($manifest), true);

        if (! is_array($installed) || ! is_string($installed['version'] ?? null)
            || ! preg_match('/^\d+\.\d+\.\d+\.\d+$/D', $installed['version'])) {
            return null;
        }

        try {
            $platform = static::platform();
        } catch (RuntimeException) {
            return null;
        }

        if (($installed['platform'] ?? null) !== $platform) {
            return null;
        }

        $path = $this->directory.'/'.$platform.'/'.$installed['version'].'/'.static::executable($platform);

        return is_file($path) ? $path : null;
    }

    /**
     * Reuse an installed browser, or download and select the requested release.
     */
    public function install(?string $version = null, bool $update = false): string
    {
        if ($update && $version !== null) {
            throw new InvalidArgumentException('Use either --update or --browser-version, not both.');
        }

        if ($version !== null && ! preg_match('/^\d+\.\d+\.\d+\.\d+$/D', $version)) {
            throw new InvalidArgumentException('Specify a complete Chrome version, such as 155.0.8059.79.');
        }

        $platform = static::platform();
        $files = new Filesystem;
        $files->mkdir($this->directory, 0700);
        $lock = fopen($this->directory.'/install.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('Unable to open the Chrome installation lock.');
        }

        $staging = null;

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another Chrome installation is in progress. Try again when it finishes.');
            }

            if ($version === null && ! $update && ($path = $this->path()) !== null) {
                $this->verify($path);

                return $path;
            }

            // A pinned installation can be reused without contacting Google.
            if ($version !== null) {
                $path = $this->directory.'/'.$platform.'/'.$version.'/'.static::executable($platform);

                if (is_file($path)) {
                    $this->verify($path);
                    $this->activate($platform, $version);

                    return $path;
                }
            }

            $release = $this->release($version);
            $version = $release['version'];
            $target = $this->directory.'/'.$platform.'/'.$version;
            $path = $target.'/'.static::executable($platform);

            if (! is_file($path)) {
                $url = $this->downloadUrl($release, $platform);

                if (! class_exists(ZipArchive::class)) {
                    throw new RuntimeException('The PHP zip extension is required to install Chrome.');
                }

                $staging = $this->directory.'/.install-'.bin2hex(random_bytes(8));
                $files->mkdir($staging, 0700);
                $this->http->request('GET', $url, [
                    'sink' => $staging.'/chrome.zip',
                    'connect_timeout' => 15,
                    'timeout' => 300,
                    'allow_redirects' => false,
                ]);

                (new ChromeArchive)->extract($staging.'/chrome.zip', $staging.'/browser');
                $this->verify($staging.'/browser/'.static::executable($platform));
                $files->mkdir(dirname($target), 0700);

                if (file_exists($target)) {
                    throw new RuntimeException("An incomplete installation exists at [{$target}]. Remove it before retrying.");
                }

                $files->rename($staging.'/browser', $target);
            } else {
                $this->verify($path);
            }

            $this->activate($platform, $version);

            return $path;
        } finally {
            try {
                if ($staging !== null) {
                    $files->remove($staging);
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * Detect the architecture of the host, including Apple Silicon.
     */
    public static function platform(?string $os = null, ?string $architecture = null): string
    {
        $os ??= PHP_OS_FAMILY;
        $architecture = strtolower($architecture ?? php_uname('m'));

        return match (true) {
            $os === 'Linux' && in_array($architecture, ['x86_64', 'amd64']) => 'linux64',
            $os === 'Linux' && in_array($architecture, ['aarch64', 'arm64']) => 'linux-arm64',
            $os === 'Darwin' && in_array($architecture, ['x86_64', 'amd64']) => 'mac-x64',
            $os === 'Darwin' && in_array($architecture, ['aarch64', 'arm64']) => 'mac-arm64',
            $os === 'Windows' && in_array($architecture, ['x86_64', 'amd64']) => 'win64',
            $os === 'Windows' && in_array($architecture, ['x86', 'i386', 'i686']) => 'win32',
            default => throw new RuntimeException("Chrome for Testing does not support [{$os}/{$architecture}]. Configure a system Chromium binary instead."),
        };
    }

    /**
     * Get the executable's path within an official Chrome archive.
     */
    public static function executable(string $platform): string
    {
        return match ($platform) {
            'linux64', 'linux-arm64' => "chrome-{$platform}/chrome",
            'mac-x64', 'mac-arm64' => "chrome-{$platform}/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing",
            'win32', 'win64' => "chrome-{$platform}/chrome.exe",
            default => throw new InvalidArgumentException('Unsupported Chrome platform.'),
        };
    }

    /**
     * Resolve a complete release from Google's official manifests.
     */
    protected function release(?string $version): array
    {
        $response = $this->http->request('GET', static::MANIFEST_URL.($version
            ? $version.'.json'
            : 'last-known-good-versions-with-downloads.json'), [
                'connect_timeout' => 15,
                'timeout' => 30,
                'allow_redirects' => false,
            ]);

        $manifest = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $release = $version ? $manifest : ($manifest['channels']['Stable'] ?? null);

        if (! is_array($release) || ! is_string($release['version'] ?? null)
            || ! preg_match('/^\d+\.\d+\.\d+\.\d+$/D', $release['version'])
            || ($version !== null && $release['version'] !== $version)) {
            throw new RuntimeException('Google returned an invalid Chrome release manifest.');
        }

        return $release;
    }

    /**
     * Only accept the official archive for the requested version and platform.
     */
    protected function downloadUrl(array $release, string $platform): string
    {
        $expected = "https://storage.googleapis.com/chrome-for-testing-public/{$release['version']}/{$platform}/chrome-{$platform}.zip";

        foreach ($release['downloads']['chrome'] ?? [] as $download) {
            if (($download['platform'] ?? null) === $platform && ($download['url'] ?? null) === $expected) {
                return $expected;
            }
        }

        throw new RuntimeException("Chrome {$release['version']} is unavailable for [{$platform}]. Choose another version or configure a system Chromium binary.");
    }

    /**
     * Ensure the binary can run before making it the selected installation.
     */
    protected function verify(string $path): void
    {
        if (! is_file($path)) {
            throw new RuntimeException('The Chrome archive does not contain the expected executable.');
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $this->verifyOnWindows($path);
            } else {
                (new Process([$path, '--version'], timeout: 15))->mustRun();
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Chrome could not run. Install the system libraries required by Chrome, or configure a compatible system Chromium binary. '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Verify Windows Chrome with a blank page because --version does not exit.
     */
    protected function verifyOnWindows(string $path): void
    {
        $profile = sys_get_temp_dir().'/screenshot-verify-'.bin2hex(random_bytes(8));

        try {
            (new Process([
                $path,
                '--headless',
                '--dump-dom',
                '--no-first-run',
                '--no-default-browser-check',
                '--user-data-dir='.$profile,
                'about:blank',
            ], timeout: 15))->mustRun();
        } finally {
            (new Filesystem)->remove($profile);
        }
    }

    /**
     * Atomically select a verified installation for subsequent application boots.
     */
    protected function activate(string $platform, string $version): void
    {
        (new Filesystem)->dumpFile($this->directory.'/installed.json', json_encode([
            'platform' => $platform,
            'version' => $version,
        ], JSON_THROW_ON_ERROR));
    }
}
