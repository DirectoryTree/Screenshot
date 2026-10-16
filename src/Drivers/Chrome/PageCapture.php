<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;
use Generator;
use HeadlessChromium\Browser;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use HeadlessChromium\Utils;
use RuntimeException;
use Throwable;

class PageCapture
{
    /**
     * Prepare to capture a page within the given browser.
     */
    public function __construct(
        protected ChromeDriver $driver,
        protected Browser $browser,
        protected CaptureOptions $options,
        protected bool $shared = false,
    ) {}

    /**
     * Prepare and capture a page, then release its resources.
     */
    public function capture(string $source, SourceType $type): ImageResult|PdfResult
    {
        $page = null;
        $failed = true;

        try {
            $page = $this->shared
                ? $this->createSharedPage()
                : $this->browser->createPage();

            $this->configurePage($page);
            $this->loadPage($page, $source, $type);

            $this->options->prepare(new CaptureContext($this->driver, $page));

            $result = $this->newCapture()->capture($page);
            $failed = false;

            return $result;
        } finally {
            $this->cleanup($page, $failed);
        }
    }

    /**
     * Apply the viewport and JavaScript settings to the page.
     */
    protected function configurePage(Page $page): void
    {
        $options = $this->options->browser();

        if ($this->shared) {
            $page->setViewport($options->getWidth(), $options->getHeight())->await($options->getBrowserTimeout());
        }

        $response = $page->getSession()->sendMessageSync(
            new Message('Emulation.setScriptExecutionDisabled', ['value' => ! $options->getJavascript()]),
            $options->getBrowserTimeout(),
        );

        if (! $response->isSuccessful()) {
            throw new RuntimeException('Unable to configure JavaScript execution: '.$response->getErrorMessage());
        }
    }

    /**
     * Load the source and stop loading after an allowed timeout.
     */
    protected function loadPage(Page $page, string $source, SourceType $type): void
    {
        $options = $this->options->browser();

        try {
            if ($type === SourceType::Html) {
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
    }

    /**
     * Create a capture for the selected output format.
     */
    protected function newCapture(): CapturesPage
    {
        $options = $this->options;

        return match ($options->format()) {
            OutputFormat::Png, OutputFormat::Jpeg => new ImageCapture($options),
            OutputFormat::Pdf => new PdfCapture($options),
        };
    }

    /**
     * Retain ownership of a shared target until its page is fully initialized.
     */
    protected function createSharedPage(): Page
    {
        $connection = $this->browser->getConnection();

        $response = $connection->sendMessageSync(
            new Message('Target.createTarget', ['url' => 'about:blank'])
        );

        if (! $response->isSuccessful() || ! is_string($target = $response['result']['targetId'] ?? null)) {
            throw new RuntimeException('Unable to create the screenshot page.');
        }

        try {
            return $this->browser->getPage($target) ?? throw new RuntimeException('Unable to initialize the screenshot page.');
        } catch (Throwable $e) {
            try {
                $connection->sendMessageSync(new Message('Target.closeTarget', ['targetId' => $target]));

                // Chrome can acknowledge closure before the target is destroyed.
                Utils::tryWithTimeout(
                    $connection->getSendSyncDefaultTimeout() * 1000,
                    $this->waitForTargetToClose($target),
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
    protected function waitForTargetToClose(string $target): Generator
    {
        while ($this->browser->getTarget($target)) {
            yield 1000;

            $this->browser->getConnection()->readData();
        }
    }

    /**
     * Release the page without replacing a capture exception.
     */
    protected function cleanup(?Page $page, bool $failed): void
    {
        if (! $page) {
            return;
        }

        try {
            $page->close();
        } catch (Throwable) {
            try {
                $response = $this->browser->getConnection()->sendMessageSync(new Message('Target.closeTarget', [
                    'targetId' => $page->getSession()->getTargetId(),
                ]));

                if ($this->shared && ! $response->isSuccessful()) {
                    throw new RuntimeException('Unable to close the screenshot page: '.$response->getErrorMessage());
                }
            } catch (Throwable $e) {
                if ($this->shared && ! $failed) {
                    throw $e;
                }
            }
        }
    }
}
