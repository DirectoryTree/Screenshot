<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use HeadlessChromium\Clip;
use HeadlessChromium\Page;
use RuntimeException;

class ImageCapture implements CapturesPage
{
    /**
     * Create an image capture strategy.
     *
     * @param  CaptureOptions<ImageOptions>  $options
     */
    public function __construct(
        protected CaptureOptions $options,
    ) {}

    /**
     * Capture the selected element, full page, or viewport within the limits.
     */
    public function capture(Page $page): ImageResult
    {
        $options = $this->options->output();

        $clip = $this->clip($page);

        $parameters = [
            'format' => $options->getFormat()->value,
            'captureBeyondViewport' => true,
            'clip' => new Clip(
                $clip->getX(), $clip->getY(),
                max(1, min($clip->getWidth(), $options->getMaxWidth())),
                max(1, min($clip->getHeight(), $options->getMaxHeight())),
            ),
        ];

        if ($options->getFormat() === OutputFormat::Jpeg) {
            $parameters['quality'] = $options->getQuality();
        }

        return new ImageResult(
            $page->screenshot($parameters)->getBase64(
                $this->options->browser()->getBrowserTimeout()
            )
        );
    }

    /**
     * Resolve the selected element, full page, or viewport bounds.
     */
    protected function clip(Page $page): Clip
    {
        if ($this->options->element() !== null) {
            return $this->getElementClip($page);
        }

        $browser = $this->options->browser();

        if ($this->options->output()->getFullPage()) {
            return $page->getFullPageClip($browser->getBrowserTimeout());
        }

        return new Clip(0, 0, $browser->getWidth(), $browser->getHeight());
    }

    /**
     * Get the element's border bounds using Chrome's DOM API.
     */
    protected function getElementClip(Page $page): Clip
    {
        $selector = $this->options->element();

        $element = $page->dom()->querySelector($selector)
            ?? throw new RuntimeException("No element matches the selector [{$selector}].");

        $position = $element->getPosition('border');

        if (! $position || $position->getWidth() <= 0 || $position->getHeight() <= 0) {
            throw new RuntimeException("The element [{$selector}] has no layout bounds.");
        }

        return new Clip($position->getX(), $position->getY(), $position->getWidth(), $position->getHeight());
    }
}
