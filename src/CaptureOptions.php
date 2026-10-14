<?php

namespace DirectoryTree\Screenshot;

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
        public BrowserOptions $browser,
        public ImageOptions|PdfOptions $output,
        public ?string $element = null,
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
     * Get the selected output format.
     */
    public function getFormat(): OutputFormat
    {
        return $this->output->getFormat();
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
