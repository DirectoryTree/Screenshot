<?php

use DirectoryTree\Screenshot\Support\ChromeInstaller;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/screenshot-installer-'.bin2hex(random_bytes(8));
    (new Filesystem)->mkdir($this->directory);
});

afterEach(function () {
    (new Filesystem)->remove($this->directory);
});

function chromeReleaseFixture(string $version = '155.0.8059.79', ?string $platform = null): array
{
    $platform ??= ChromeInstaller::platform();

    return ['version' => $version, 'downloads' => ['chrome' => [[
        'platform' => $platform,
        'url' => "https://storage.googleapis.com/chrome-for-testing-public/{$version}/{$platform}/chrome-{$platform}.zip",
    ]]]];
}

function chromeArchiveFixture(string $directory, string $script = "#!/bin/sh\necho 'Google Chrome for Testing 155.0.8059.79'\n"): string
{
    $path = $directory.'/fixture.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(ChromeInstaller::executable(ChromeInstaller::platform()), $script);
    $zip->setExternalAttributesIndex(0, ZipArchive::OPSYS_UNIX, 0100755 << 16);
    $zip->close();

    return file_get_contents($path);
}

test('platform detection maps supported operating systems and architectures', function ($os, $architecture, $platform) {
    expect(ChromeInstaller::platform($os, $architecture))->toBe($platform);
})->with([
    ['Linux', 'x86_64', 'linux64'],
    ['Linux', 'aarch64', 'linux-arm64'],
    ['Darwin', 'arm64', 'mac-arm64'],
    ['Darwin', 'x86_64', 'mac-x64'],
    ['Windows', 'AMD64', 'win64'],
    ['Windows', 'i686', 'win32'],
]);

test('unsupported platforms are rejected instead of downloading an incompatible binary', function () {
    expect(fn () => ChromeInstaller::platform('Linux', 'armv7l'))->toThrow(RuntimeException::class, 'does not support');
    expect(fn () => ChromeInstaller::platform('Windows', 'ARM64'))->toThrow(RuntimeException::class, 'does not support');
});

test('the stable browser is installed and selected and pinned installs work offline', function () {
    $release = chromeReleaseFixture();
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], json_encode(['channels' => ['Stable' => $release]])),
        new Response(200, [], chromeArchiveFixture($this->directory)),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $installer = new ChromeInstaller($this->directory, new Client(['handler' => $stack]));

    expect($installer->path())->toBeNull();
    $path = $installer->install();
    expect($path)->toBe($installer->path())
        ->and(is_executable($path))->toBeTrue()
        ->and($installer->install($release['version']))->toBe($path)
        ->and($history)->toHaveCount(2)
        ->and((string) $history[0]['request']->getUri())->toEndWith('last-known-good-versions-with-downloads.json')
        ->and(glob($this->directory.'/.install-*'))->toBe([]);
});

test('stable installs check for updates but reuse the already downloaded version', function () {
    $release = chromeReleaseFixture();
    $manifest = new Response(200, [], json_encode(['channels' => ['Stable' => $release]]));
    $mock = new MockHandler([$manifest, new Response(200, [], chromeArchiveFixture($this->directory)), $manifest]);
    $installer = new ChromeInstaller($this->directory, new Client(['handler' => HandlerStack::create($mock)]));

    expect($installer->install())->toBe($installer->install())
        ->and($mock->count())->toBe(0);
});

test('failed upgrades preserve the selected installation and remove temporary downloads', function ($response) {
    $first = chromeReleaseFixture();
    $next = chromeReleaseFixture('156.0.8078.12');
    $mock = new MockHandler([
        new Response(200, [], json_encode($first)),
        new Response(200, [], chromeArchiveFixture($this->directory)),
        new Response(200, [], json_encode($next)),
        $response,
    ]);
    $installer = new ChromeInstaller($this->directory, new Client(['handler' => HandlerStack::create($mock)]));
    $path = $installer->install($first['version']);

    expect(fn () => $installer->install($next['version']))->toThrow(RuntimeException::class);
    expect($installer->path())->toBe($path)
        ->and(is_file($path))->toBeTrue()
        ->and(glob($this->directory.'/.install-*'))->toBe([]);
})->with([
    'corrupt archive' => [new Response(200, [], 'not a zip')],
    'download failure' => [new Response(503)],
]);

test('a browser that cannot run is not activated', function () {
    $release = chromeReleaseFixture();
    $mock = new MockHandler([
        new Response(200, [], json_encode($release)),
        new Response(200, [], chromeArchiveFixture($this->directory, "#!/bin/sh\necho 'missing library' >&2\nexit 127\n")),
    ]);
    $installer = new ChromeInstaller($this->directory, new Client(['handler' => HandlerStack::create($mock)]));

    expect(fn () => $installer->install($release['version']))->toThrow(RuntimeException::class, 'system libraries');
    expect($installer->path())->toBeNull()
        ->and(glob($this->directory.'/.install-*'))->toBe([]);
});

test('unsafe or mismatched manifests cannot choose an arbitrary download', function ($release) {
    $mock = new MockHandler([new Response(200, [], json_encode($release))]);
    $installer = new ChromeInstaller($this->directory, new Client(['handler' => HandlerStack::create($mock)]));

    expect(fn () => $installer->install('155.0.8059.79'))->toThrow(RuntimeException::class);
    expect($installer->path())->toBeNull();
})->with([
    'invalid version' => [['version' => '../escape']],
    'mismatched version' => [chromeReleaseFixture('156.0.8078.12')],
    'missing platform' => [chromeReleaseFixture(platform: 'nonexistent')],
    'untrusted url' => [['version' => '155.0.8059.79', 'downloads' => ['chrome' => [[
        'platform' => ChromeInstaller::platform(), 'url' => 'https://example.com/chrome.zip',
    ]]]]],
]);

test('installation refuses concurrent writers and accepts a retry after the lock is released', function () {
    $lock = fopen($this->directory.'/install.lock', 'c');
    flock($lock, LOCK_EX);
    $mock = new MockHandler([new Response(200, [], json_encode(chromeReleaseFixture())), new Response(200, [], chromeArchiveFixture($this->directory))]);
    $installer = new ChromeInstaller($this->directory, new Client(['handler' => HandlerStack::create($mock)]));

    try {
        expect(fn () => $installer->install())->toThrow(RuntimeException::class, 'in progress');
        expect($mock->count())->toBe(2);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    expect(is_file($installer->install('155.0.8059.79')))->toBeTrue();
});

test('invalid version selectors are rejected before any HTTP request', function ($version) {
    $installer = new ChromeInstaller($this->directory, new Client(['handler' => HandlerStack::create(new MockHandler)]));

    expect(fn () => $installer->install($version))->toThrow(InvalidArgumentException::class);
})->with(['stable', '155', '../155.0.1.2', "155.0.1.2\n", '']);

test('missing or invalid installation records allow normal browser discovery', function ($contents) {
    file_put_contents($this->directory.'/installed.json', $contents);

    expect((new ChromeInstaller($this->directory))->path())->toBeNull();
})->with(['{}', 'invalid json', '{"version":"../escape"}', '{"platform":"unknown","version":"155.0.8059.79"}']);
