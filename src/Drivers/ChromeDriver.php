<?php

namespace DirectoryTree\Screenshot\Drivers;

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowsers;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\OutputFormat;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use Generator;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Clip;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use HeadlessChromium\Utils;
use InvalidArgumentException;
use RuntimeException;
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
    public function capture(string $source, string $type, ImageOptions|PdfOptions $options): ImageResult|PdfResult
    {
        $options->validate();

        if (! in_array($type, ['html', 'url'], true)) {
            throw new InvalidArgumentException('The source type must be html or url.');
        }

        return $this->withBrowser(
            $options,
            fn (Browser $browser, bool $shared) => $this->capturePage($browser, $source, $type, $options, $shared),
        );
    }

    /**
     * Run a capture in a dedicated browser or under a shared socket lease.
     */
    protected function withBrowser(BrowserOptions $options, callable $callback): mixed
    {
        if ($socket = $this->socket($options)) {
            return $socket->run(
                fn () => $this->createBrowser($options),
                fn (Browser $browser) => $callback($browser, true),
                array_merge($this->browserOptions, ['sendSyncDefaultTimeout' => $options->getBrowserTimeout()]),
            );
        }

        return $callback($this->createBrowser($options), false);
    }

    /**
     * Capture the selected output format from a prepared page.
     */
    protected function capturePage(Browser $browser, string $source, string $type, ImageOptions|PdfOptions $options, bool $shared = false): ImageResult|PdfResult
    {
        return $this->withPage($browser, $source, $type, $options, $shared, function (Page $page) use ($options) {
            if ($options->getFormat() === OutputFormat::Pdf) {
                return new PdfResult($page->pdf($options->toArray())->getBase64($options->getBrowserTimeout()));
            }

            $clip = $options->getFullPage()
                ? $page->getFullPageClip($options->getBrowserTimeout())
                : new Clip(0, 0, $options->getWidth(), $options->getHeight());

            $parameters = [
                'format' => $options->getFormat()->value,
                'captureBeyondViewport' => true,
                'clip' => new Clip(
                    0, 0,
                    max(1, min($clip->getWidth(), $options->getMaxWidth())),
                    max(1, min($clip->getHeight(), $options->getMaxHeight())),
                ),
            ];

            if ($options->getFormat() === OutputFormat::Jpeg) {
                $parameters['quality'] = $options->getQuality();
            }

            return new ImageResult($page->screenshot($parameters)->getBase64($options->getBrowserTimeout()));
        });
    }

    /**
     * Load a page and release its resources after rendering either format.
     */
    protected function withPage(Browser $browser, string $source, string $type, BrowserOptions $options, bool $shared, callable $render): mixed
    {
        $page = null;
        $failed = true;

        try {
            if ($shared && isset($this->browserOptions['headers'])) {
                $browser->getConnection()->setConnectionHttpHeaders($this->browserOptions['headers']);
            }

            $page = $shared ? $this->createSharedPage($browser) : $browser->createPage();

            if ($shared) {
                $page->setViewport($options->getWidth(), $options->getHeight())->await($options->getBrowserTimeout());
            }

            $response = $page->getSession()->sendMessageSync(
                new Message('Emulation.setScriptExecutionDisabled', ['value' => ! $options->getJavascript()]),
                $options->getBrowserTimeout(),
            );

            if (! $response->isSuccessful()) {
                throw new RuntimeException('Unable to configure JavaScript execution: '.$response->getErrorMessage());
            }

            try {
                if ($type === 'html') {
                    $page->setHtml($source, $options->getLoadTimeout());
                } else {
                    $page->navigate($source)->waitForNavigation(Page::LOAD, $options->getLoadTimeout());
                }
            } catch (OperationTimedOut $e) {
                if ($options->getFailOnTimeout()) {
                    throw $e;
                }

                $response = $page->getSession()->sendMessageSync(new Message('Page.stopLoading'), $options->getBrowserTimeout());

                if (! $response->isSuccessful()) {
                    throw new RuntimeException('Unable to stop page loading: '.$response->getErrorMessage(), previous: $e);
                }
            }

            $result = $render($page);
            $failed = false;

            return $result;
        } finally {
            $this->cleanup($browser, $page, $failed, $shared);
        }
    }

    /**
     * Retain ownership of a shared target until its page is fully initialized.
     */
    protected function createSharedPage(Browser $browser): Page
    {
        $connection = $browser->getConnection();
        $response = $connection->sendMessageSync(new Message('Target.createTarget', ['url' => 'about:blank']));

        if (! $response->isSuccessful() || ! is_string($target = $response['result']['targetId'] ?? null)) {
            throw new RuntimeException('Unable to create the screenshot page.');
        }

        try {
            return $browser->getPage($target) ?? throw new RuntimeException('Unable to initialize the screenshot page.');
        } catch (Throwable $e) {
            try {
                $connection->sendMessageSync(new Message('Target.closeTarget', ['targetId' => $target]));

                // Chrome can acknowledge closure before the target is destroyed.
                Utils::tryWithTimeout(
                    $connection->getSendSyncDefaultTimeout() * 1000,
                    $this->waitForTargetToClose($browser, $target),
                );
            } catch (Throwable) {
                // Preserve the initialization failure without closing other workers' pages.
            }

            throw $e;
        }
    }

    /**
     * Wait for Chrome to confirm that the target was destroyed.
     */
    protected function waitForTargetToClose(Browser $browser, string $target): Generator
    {
        while ($browser->getTarget($target)) {
            yield 1000;

            $browser->getConnection()->readData();
        }
    }

    /**
     * Start a dedicated browser or a persistent shared browser.
     */
    protected function createBrowser(BrowserOptions $options): Browser
    {
        $socket = $this->socket($options);
        $browserOptions = $this->browserOptions;

        if ($socket && empty($browserOptions['userDataDir'])) {
            $browserOptions['userDataDir'] = $socket->path().'.profile';
        }

        return (new BrowserFactory($this->binary))->createBrowser(array_merge($browserOptions, [
            'windowSize' => [$options->getWidth(), $options->getHeight()],
            'sendSyncDefaultTimeout' => $options->getBrowserTimeout(),
            'keepAlive' => $socket !== null,
        ]));
    }

    /**
     * Resolve the shared socket selected for this capture.
     */
    protected function socket(BrowserOptions $options): ?BrowserSocket
    {
        return $options->getSocket() !== null
            ? BrowserSocket::named($options->getSocket(), $this->socketDirectory, $this->lockTimeout)
            : null;
    }

    /**
     * Wait for active captures and stop a named shared browser.
     */
    public function close(string $key): void
    {
        BrowserSocket::named($key, $this->socketDirectory, $this->lockTimeout)->close($this->browserOptions);
    }

    /**
     * Release the page and browser without replacing a capture exception.
     */
    protected function cleanup(Browser $browser, ?Page $page, bool $failed, bool $shared = false): void
    {
        if ($page) {
            try {
                $page->close();
            } catch (Throwable) {
                try {
                    $response = $browser->getConnection()->sendMessageSync(new Message('Target.closeTarget', [
                        'targetId' => $page->getSession()->getTargetId(),
                    ]));

                    if ($shared && ! $response->isSuccessful()) {
                        throw new RuntimeException('Unable to close the screenshot page: '.$response->getErrorMessage());
                    }
                } catch (Throwable $e) {
                    if ($shared && ! $failed) {
                        throw $e;
                    }
                }
            }
        }

        if ($shared) {
            return;
        }

        try {
            $browser->close();
        } catch (Throwable $e) {
            if (! $failed) {
                throw $e;
            }
        }
    }
}
