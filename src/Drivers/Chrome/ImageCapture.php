<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use HeadlessChromium\Clip;
use HeadlessChromium\Page;

class ImageCapture implements CapturesPage
{
    /**
     * Create an image capture strategy.
     */
    public function __construct(
        protected BrowserOptions $browser,
        protected ImageOptions $options
    ) {}

    /**
     * Capture the full page or viewport within the configured limits.
     */
    public function capture(Page $page): ImageResult
    {
        $clip = $this->options->getFullPage()
            ? $page->getFullPageClip($this->browser->getBrowserTimeout())
            : new Clip(0, 0, $this->browser->getWidth(), $this->browser->getHeight());

        $parameters = [
            'format' => $this->options->getFormat()->value,
            'captureBeyondViewport' => true,
            'clip' => new Clip(
                0, 0,
                max(1, min($clip->getWidth(), $this->options->getMaxWidth())),
                max(1, min($clip->getHeight(), $this->options->getMaxHeight())),
            ),
        ];

        if ($this->options->getFormat() === OutputFormat::Jpeg) {
            $parameters['quality'] = $this->options->getQuality();
        }

        return new ImageResult(
            $page->screenshot($parameters)->getBase64(
                $this->browser->getBrowserTimeout()
            )
        );
    }
}
