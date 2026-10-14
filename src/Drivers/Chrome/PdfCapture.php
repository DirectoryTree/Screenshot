<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use HeadlessChromium\Page;
use RuntimeException;

class PdfCapture implements CapturesPage
{
    /**
     * Create a PDF capture strategy.
     */
    public function __construct(
        protected BrowserOptions $browser,
        protected PdfOptions $options,
        protected ?string $element = null,
    ) {}

    /**
     * Print the prepared page using the configured paper and layout.
     */
    public function capture(Page $page): PdfResult
    {
        if ($this->element !== null) {
            $this->isolateElement($page);
        }

        return new PdfResult(
            $page->pdf(
                $this->options->toArray()
            )->getBase64(
                $this->browser->getBrowserTimeout()
            )
        );
    }

    /**
     * Move the selected content into the body, preserving document stylesheets.
     */
    protected function isolateElement(Page $page): void
    {
        $selector = json_encode($this->element, JSON_THROW_ON_ERROR);

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
            JS)->getReturnValue($this->browser->getBrowserTimeout());

        if (! $found) {
            throw new RuntimeException("No element matches the selector [{$this->element}].");
        }
    }
}
