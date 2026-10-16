<?php

namespace DirectoryTree\Screenshot;

use Closure;
use DirectoryTree\Screenshot\Contracts\CaptureContext;
use DirectoryTree\Screenshot\Enums\OutputFormat;

/**
 * @template TOutput of ImageOptions|PdfOptions
 */
class CaptureOptions
{
    /**
     * Combine browser and output settings with an optional element selector.
     *
     * @param  TOutput  $output
     */
    public function __construct(
        protected BrowserOptions $browser,
        protected ImageOptions|PdfOptions $output,
        protected ?string $element = null,
        protected array $callbacks = [],
    ) {}

    /**
     * Copy both sets of settings when taking a capture snapshot.
     */
    public function __clone(): void
    {
        $this->browser = clone $this->browser;
        $this->output = clone $this->output;
    }

    /**
     * Get the browser settings.
     */
    public function browser(): BrowserOptions
    {
        return $this->browser;
    }

    /**
     * Get the output settings.
     *
     * @return TOutput
     */
    public function output(): ImageOptions|PdfOptions
    {
        return $this->output;
    }

    /**
     * Get the selected CSS selector.
     */
    public function element(): ?string
    {
        return $this->element;
    }

    /**
     * Get the selected output format.
     */
    public function format(): OutputFormat
    {
        return $this->output->getFormat();
    }

    /**
     * Register a callback to customize the driver before capturing.
     */
    public function before(Closure $callback): static
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Invoke the callbacks with the prepared driver context.
     */
    public function prepare(CaptureContext $context): void
    {
        foreach ($this->callbacks as $callback) {
            $callback($context);
        }
    }

    /**
     * Validate the browser and output settings before capturing.
     */
    public function validate(): void
    {
        $this->browser->validate();
        $this->output->validate();
    }
}
