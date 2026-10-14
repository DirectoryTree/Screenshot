<?php

namespace DirectoryTree\Screenshot\Drivers;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\ScreenshotResult;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Clip;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ChromeDriver implements Driver
{
    /**
     * Create a new Chrome driver.
     */
    public function __construct(
        protected ?string $binary = null,
        protected array $browserOptions = [],
    ) {}

    /**
     * Capture an image using a dedicated browser process.
     */
    public function capture(string $source, string $type, CaptureOptions $options): ScreenshotResult
    {
        $options->validate();

        if (! in_array($type, ['html', 'url'], true)) {
            throw new InvalidArgumentException('The source type must be html or url.');
        }

        $browser = $this->createBrowser($options);
        $page = null;
        $failed = true;

        try {
            $page = $browser->createPage();

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
            $this->close($browser, $page, $failed);
        }
    }

    /**
     * Start a browser that is owned by this capture operation.
     */
    protected function createBrowser(CaptureOptions $options): Browser
    {
        return (new BrowserFactory($this->binary))->createBrowser(array_merge($this->browserOptions, [
            'windowSize' => [$options->width, $options->height],
            'sendSyncDefaultTimeout' => $options->timeout,
            'keepAlive' => false,
        ]));
    }

    /**
     * Release the page and browser without replacing a capture exception.
     */
    protected function close(Browser $browser, ?Page $page, bool $failed): void
    {
        if ($page) {
            try {
                $page->close();
            } catch (Throwable) {
                try {
                    $browser->getConnection()->sendMessageSync(new Message('Target.closeTarget', [
                        'targetId' => $page->getSession()->getTargetId(),
                    ]));
                } catch (Throwable) {
                    // Closing the owned browser also releases any remaining targets.
                }
            }
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
