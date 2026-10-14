<?php

namespace DirectoryTree\Screenshot\Drivers;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowsers;
use DirectoryTree\Screenshot\ScreenshotResult;
use DirectoryTree\Screenshot\Support\BrowserSocket;
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
     * Capture an image using a dedicated or shared browser process.
     */
    public function capture(string $source, string $type, CaptureOptions $options): ScreenshotResult
    {
        $options->validate();

        if (! in_array($type, ['html', 'url'], true)) {
            throw new InvalidArgumentException('The source type must be html or url.');
        }

        if ($socket = $this->socket($options)) {
            return $socket->run(
                fn () => $this->createBrowser($options),
                fn (Browser $browser) => $this->capturePage($browser, $source, $type, $options, shared: true),
                array_merge($this->browserOptions, ['sendSyncDefaultTimeout' => $options->getTimeout()]),
            );
        }

        return $this->capturePage($this->createBrowser($options), $source, $type, $options);
    }

    /**
     * Capture and release only this worker's page.
     */
    protected function capturePage(Browser $browser, string $source, string $type, CaptureOptions $options, bool $shared = false): ScreenshotResult
    {
        $page = null;
        $failed = true;

        try {
            if ($shared && isset($this->browserOptions['headers'])) {
                $browser->getConnection()->setConnectionHttpHeaders($this->browserOptions['headers']);
            }

            $page = $shared ? $this->createSharedPage($browser) : $browser->createPage();

            if ($shared) {
                $page->setViewport($options->getWidth(), $options->getHeight())->await($options->getTimeout());
            }

            $response = $page->getSession()->sendMessageSync(
                new Message('Emulation.setScriptExecutionDisabled', ['value' => ! $options->getJavascript()]),
                $options->getTimeout(),
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

                $response = $page->getSession()->sendMessageSync(new Message('Page.stopLoading'), $options->getTimeout());

                if (! $response->isSuccessful()) {
                    throw new RuntimeException('Unable to stop page loading: '.$response->getErrorMessage(), previous: $e);
                }
            }

            $clip = $options->getFullPage()
                ? $page->getFullPageClip($options->getTimeout())
                : new Clip(0, 0, $options->getWidth(), $options->getHeight());

            $parameters = [
                'format' => $options->getFormat(),
                'captureBeyondViewport' => true,
                'clip' => new Clip(
                    0, 0,
                    max(1, min($clip->getWidth(), $options->getMaxWidth())),
                    max(1, min($clip->getHeight(), $options->getMaxHeight())),
                ),
            ];

            if ($options->getFormat() === 'jpeg') {
                $parameters['quality'] = $options->getQuality();
            }

            $result = new ScreenshotResult($page->screenshot($parameters)->getBase64($options->getTimeout()));
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
                Utils::tryWithTimeout($connection->getSendSyncDefaultTimeout() * 1000, (function () use ($browser, $connection, $target) {
                    while ($browser->getTarget($target)) {
                        yield 1000;

                        $connection->readData();
                    }
                })());
            } catch (Throwable) {
                // Preserve the initialization failure without closing other workers' pages.
            }

            throw $e;
        }
    }

    /**
     * Start a dedicated browser or a persistent shared browser.
     */
    protected function createBrowser(CaptureOptions $options): Browser
    {
        $socket = $this->socket($options);
        $browserOptions = $this->browserOptions;

        if ($socket && empty($browserOptions['userDataDir'])) {
            $browserOptions['userDataDir'] = $socket->path().'.profile';
        }

        return (new BrowserFactory($this->binary))->createBrowser(array_merge($browserOptions, [
            'windowSize' => [$options->getWidth(), $options->getHeight()],
            'sendSyncDefaultTimeout' => $options->getTimeout(),
            'keepAlive' => $socket !== null,
        ]));
    }

    /**
     * Resolve the shared socket selected for this capture.
     */
    protected function socket(CaptureOptions $options): ?BrowserSocket
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
