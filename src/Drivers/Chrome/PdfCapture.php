<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use HeadlessChromium\Page;
use RuntimeException;

class PdfCapture implements CapturesPage
{
    /**
     * Create a PDF capture strategy.
     *
     * @param  CaptureOptions<PdfOptions>  $options
     */
    public function __construct(
        protected CaptureOptions $options,
    ) {}

    /**
     * Print the prepared page using the configured paper and layout.
     */
    public function capture(Page $page): PdfResult
    {
        if ($this->options->element() !== null) {
            $this->isolateElement($page);
        }

        return new PdfResult(
            $page->pdf(
                $this->options->output()->toArray()
            )->getBase64(
                $this->options->browser()->getBrowserTimeout()
            )
        );
    }

    /**
     * Move the selected content into the body, preserving document stylesheets.
     */
    protected function isolateElement(Page $page): void
    {
        $element = $this->options->element();

        $selector = json_encode($element, JSON_THROW_ON_ERROR);

        $found = $page->evaluate(<<<JS
            (() => {
                const element = document.querySelector({$selector});

                if (!element) return false;
                if (element === document.body || element === document.documentElement) return true;

                for (const stylesheet of document.querySelectorAll('body style, body link[rel~="stylesheet"]')) {
                    if (!element.contains(stylesheet)) document.head.appendChild(stylesheet);
                }

                document.body.replaceChildren(element);

                return true;
            })()
            JS)->getReturnValue($this->options->browser()->getBrowserTimeout());

        if (! $found) {
            throw new RuntimeException("No element matches the selector [{$element}].");
        }
    }
}
