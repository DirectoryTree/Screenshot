<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use HeadlessChromium\Page;

class PdfCapture implements CapturesPage
{
    /**
     * Create a PDF capture strategy.
     */
    public function __construct(
        protected PdfOptions $options
    ) {}

    /**
     * Print the prepared page using the configured paper and layout.
     */
    public function capture(Page $page): PdfResult
    {
        return new PdfResult(
            $page->pdf(
                $this->options->toArray()
            )->getBase64(
                $this->options->getBrowserTimeout()
            )
        );
    }
}
