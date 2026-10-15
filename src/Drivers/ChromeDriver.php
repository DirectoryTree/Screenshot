<?php

namespace DirectoryTree\Screenshot\Drivers;

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowsers;
use DirectoryTree\Screenshot\Drivers\Chrome\PageCapture;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use Throwable;

class ChromeDriver implements Driver, ManagesBrowsers
{
    /**
     * Create a new Chrome driver.
     */
    public function __construct(
        protected ?string $binary = null,
        protected array $browserOptions = [],
        protected ?string $socketDirectory = null,
        protected int $lockTimeout = 60000,
    ) {}

    /**
     * Capture an image or PDF using a dedicated or shared browser process.
     */
    public function capture(string $source, SourceType $type, CaptureOptions $options): ImageResult|PdfResult
    {
        $options->validate();

        if ($socket = $this->socket($options->browser())) {
            return $socket->run(
                fn () => $this->createBrowser($options->browser()),
                fn (Browser $browser) => $this->capturePage($browser, $source, $type, $options, shared: true),
                $this->getConnectionOptions($options->browser()),
            );
        }

        return $this->capturePage($this->createBrowser($options->browser()), $source, $type, $options);
    }

    /**
     * Capture a page and close its dedicated browser after use.
     */
    protected function capturePage(Browser $browser, string $source, SourceType $type, CaptureOptions $options, bool $shared = false): ImageResult|PdfResult
    {
        $failed = true;

        try {
            if ($shared) {
                $connectionOptions = $this->getConnectionOptions($options->browser());

                if (isset($connectionOptions['headers'])) {
                    $browser->getConnection()->setConnectionHttpHeaders($connectionOptions['headers']);
                }
            }

            $result = $this->createPageCapture($browser, $options, $shared)->capture($source, $type);

            $failed = false;

            return $result;
        } finally {
            if (! $shared) {
                try {
                    $browser->close();
                } catch (Throwable $e) {
                    if (! $failed) {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Create the capture used to prepare and render a browser page.
     */
    protected function createPageCapture(Browser $browser, CaptureOptions $options, bool $shared = false): PageCapture
    {
        return new PageCapture($browser, $options, $shared);
    }

    /**
     * Start a dedicated browser or a persistent shared browser.
     */
    protected function createBrowser(BrowserOptions $options): Browser
    {
        return $this->createBrowserFactory()->createBrowser(
            $this->getBrowserOptions($options),
        );
    }

    /**
     * Create the factory used to start Chrome.
     */
    protected function createBrowserFactory(): BrowserFactory
    {
        return new BrowserFactory($this->binary);
    }

    /**
     * Prepare the options used to start a dedicated or shared browser.
     */
    protected function getBrowserOptions(BrowserOptions $options): array
    {
        $socket = $this->socket($options);

        $browserOptions = $this->getConnectionOptions($options);

        if ($socket && empty($browserOptions['userDataDir'])) {
            $browserOptions['userDataDir'] = $socket->path().'.profile';
        }

        return array_merge($browserOptions, [
            'windowSize' => [$options->getWidth(), $options->getHeight()],
            'keepAlive' => $socket !== null,
        ]);
    }

    /**
     * Prepare connection settings, using capture timeouts when available.
     */
    protected function getConnectionOptions(?BrowserOptions $options = null): array
    {
        return array_merge($this->browserOptions, $options ? [
            'sendSyncDefaultTimeout' => $options->getBrowserTimeout(),
        ] : []);
    }

    /**
     * Resolve the shared socket selected for this capture.
     */
    protected function socket(BrowserOptions $options): ?BrowserSocket
    {
        if (is_null($key = $options->getSocket())) {
            return null;
        }

        return $this->createSocket($key);
    }

    /**
     * Create the socket used to capture with or close a shared browser.
     */
    protected function createSocket(string $key): BrowserSocket
    {
        return BrowserSocket::named($key, $this->socketDirectory, $this->lockTimeout);
    }

    /**
     * Wait for active captures and stop a named shared browser.
     */
    public function close(string $key): void
    {
        $this->createSocket($key)->close($this->getConnectionOptions());
    }
}
