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
                array_merge($this->browserOptions, ['sendSyncDefaultTimeout' => $options->browser()->getBrowserTimeout()]),
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
            if ($shared && isset($this->browserOptions['headers'])) {
                $browser->getConnection()->setConnectionHttpHeaders($this->browserOptions['headers']);
            }

            $result = (new PageCapture($browser, $options, $shared))->capture($source, $type);

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

        $browserOptions = $this->browserOptions;

        if ($socket && empty($browserOptions['userDataDir'])) {
            $browserOptions['userDataDir'] = $socket->path().'.profile';
        }

        return array_merge($browserOptions, [
            'windowSize' => [$options->getWidth(), $options->getHeight()],
            'sendSyncDefaultTimeout' => $options->getBrowserTimeout(),
            'keepAlive' => $socket !== null,
        ]);
    }

    /**
     * Resolve the shared socket selected for this capture.
     */
    protected function socket(BrowserOptions $options): ?BrowserSocket
    {
        if (is_null($key = $options->getSocket())) {
            return null;
        }

        return BrowserSocket::named($key, $this->socketDirectory, $this->lockTimeout);
    }

    /**
     * Wait for active captures and stop a named shared browser.
     */
    public function close(string $key): void
    {
        BrowserSocket::named($key, $this->socketDirectory, $this->lockTimeout)->close($this->browserOptions);
    }
}
