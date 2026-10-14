<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\BrowserOptions;
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
     */
    public function __construct(
        protected BrowserOptions $browser,
        protected ImageOptions $options,
        protected ?string $element = null,
    ) {}

    /**
     * Capture the selected element, full page, or viewport within the limits.
     */
    public function capture(Page $page): ImageResult
    {
        $clip = $this->clip($page);

        $parameters = [
            'format' => $this->options->getFormat()->value,
            'captureBeyondViewport' => true,
            'clip' => new Clip(
                $clip->getX(), $clip->getY(),
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

    /**
     * Resolve the selected element, full page, or viewport bounds.
     */
    protected function clip(Page $page): Clip
    {
        if ($this->element !== null) {
            return $this->getElementClip($page);
        }

        if ($this->options->getFullPage()) {
            return $page->getFullPageClip($this->browser->getBrowserTimeout());
        }

        return new Clip(0, 0, $this->browser->getWidth(), $this->browser->getHeight());
    }

    /**
     * Get the element's border bounds using Chrome's DOM API.
     */
    protected function getElementClip(Page $page): Clip
    {
        $element = $page->dom()->querySelector($this->element)
            ?? throw new RuntimeException("No element matches the selector [{$this->element}].");

        $position = $element->getPosition('border');

        if (! $position || $position->getWidth() <= 0 || $position->getHeight() <= 0) {
            throw new RuntimeException("The element [{$this->element}] has no layout bounds.");
        }

        return new Clip($position->getX(), $position->getY(), $position->getWidth(), $position->getHeight());
    }
}
