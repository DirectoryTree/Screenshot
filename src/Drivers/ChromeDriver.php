<?php

namespace DirectoryTree\Screenshot\Drivers;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowserSockets;
use DirectoryTree\Screenshot\ScreenshotResult;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Clip;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ChromeDriver implements Driver, ManagesBrowserSockets
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
                array_merge($this->browserOptions, ['sendSyncDefaultTimeout' => $options->timeout]),
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

            $page = $browser->createPage();

            if ($shared) {
                $page->setViewport($options->width, $options->height)->await($options->timeout);
            }

            $response = $page->getSession()->sendMessageSync(
                new Message('Emulation.setScriptExecutionDisabled', ['value' => ! $options->javascript]),
                $options->timeout,
            );

            if (! $response->isSuccessful()) {
                throw new RuntimeException('Unable to configure JavaScript execution: '.$response->getErrorMessage());
            }

            try {
                if ($type === 'html') {
                    $page->setHtml($source, $options->loadTimeout);
                } else {
                    $page->navigate($source)->waitForNavigation(Page::LOAD, $options->loadTimeout);
                }
            } catch (OperationTimedOut $e) {
                if ($options->failOnTimeout) {
                    throw $e;
                }

                $response = $page->getSession()->sendMessageSync(new Message('Page.stopLoading'), $options->timeout);

                if (! $response->isSuccessful()) {
                    throw new RuntimeException('Unable to stop page loading: '.$response->getErrorMessage(), previous: $e);
                }
            }

            $clip = $options->fullPage
                ? $page->getFullPageClip($options->timeout)
                : new Clip(0, 0, $options->width, $options->height);

            $parameters = [
                'format' => $options->format,
                'captureBeyondViewport' => true,
                'clip' => new Clip(
                    0, 0,
                    max(1, min($clip->getWidth(), $options->maxWidth)),
                    max(1, min($clip->getHeight(), $options->maxHeight)),
                ),
            ];

            if ($options->format === 'jpeg') {
                $parameters['quality'] = $options->quality;
            }

            $result = new ScreenshotResult($page->screenshot($parameters)->getBase64($options->timeout));
            $failed = false;

            return $result;
        } finally {
            $this->close($browser, $page, $failed, $shared);
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
            'windowSize' => [$options->width, $options->height],
            'sendSyncDefaultTimeout' => $options->timeout,
            'keepAlive' => $socket !== null,
        ]));
    }

    /**
     * Resolve the shared socket selected for this capture.
     */
    protected function socket(CaptureOptions $options): ?BrowserSocket
    {
        return $options->socket !== null
            ? BrowserSocket::named($options->socket, $this->socketDirectory, $this->lockTimeout)
            : null;
    }

    /**
     * Wait for active captures and stop a named shared browser.
     */
    public function closeSocket(string $key): void
    {
        BrowserSocket::named($key, $this->socketDirectory, $this->lockTimeout)->close($this->browserOptions);
    }

    /**
     * Release the page and browser without replacing a capture exception.
     */
    protected function close(Browser $browser, ?Page $page, bool $failed, bool $shared = false): void
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
