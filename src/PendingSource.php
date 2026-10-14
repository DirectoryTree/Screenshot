<?php

namespace DirectoryTree\Screenshot;

class PendingSource
{
    /**
     * The CSS selector to capture from the source.
     */
    protected ?string $element = null;

    /**
     * Prepare a source with independent browser settings and shared output defaults.
     */
    public function __construct(
        protected PendingCapture $capture,
        protected BrowserOptions $browser,
        protected ImageOptions $image,
        protected PdfOptions $pdf,
    ) {
        $this->browser = clone $browser;
    }

    /**
     * Set the viewport dimensions after validating both values.
     */
    public function size(int $width, int $height): static
    {
        $this->browser->setSize($width, $height);

        return $this;
    }

    /**
     * Capture the first element matching the given CSS selector.
     */
    public function element(string $selector): static
    {
        $this->element = $selector;

        return $this;
    }

    /**
     * Set page-load and individual browser-command timeouts in milliseconds.
     */
    public function timeouts(?int $load = null, ?int $browser = null): static
    {
        $this->browser->setTimeouts($load, $browser);

        return $this;
    }

    /**
     * Allow JavaScript execution in the captured document.
     */
    public function javascript(bool $value = true): static
    {
        $this->browser->setJavascript($value);

        return $this;
    }

    /**
     * Throw when page loading times out instead of capturing partial content.
     */
    public function failOnTimeout(bool $value = true): static
    {
        $this->browser->setFailOnTimeout($value);

        return $this;
    }

    /**
     * Share a browser with other workers using the same key.
     */
    public function socket(string $key): static
    {
        $this->browser->setSocket($key);

        return $this;
    }

    /**
     * Use a dedicated browser for this capture.
     */
    public function withoutSocket(): static
    {
        $this->browser->setSocket(null);

        return $this;
    }

    /**
     * Prepare a PNG image using this source's browser settings.
     */
    public function png(): PendingImage
    {
        return (new PendingImage(
            $this->capture,
            $this->newCaptureOptions($this->image),
        ))->png();
    }

    /**
     * Prepare a JPEG image with the configured or supplied quality.
     */
    public function jpeg(?int $quality = null): PendingImage
    {
        return (new PendingImage(
            $this->capture,
            $this->newCaptureOptions($this->image),
        ))->jpeg($quality);
    }

    /**
     * Prepare a PDF using this source's browser settings.
     */
    public function pdf(): PendingPdf
    {
        return new PendingPdf(
            $this->capture,
            $this->newCaptureOptions($this->pdf),
        );
    }

    /**
     * Capture the source as an image and save it to a local path.
     */
    public function save(string $path): ImageResult
    {
        return $this->capture()->save($path);
    }

    /**
     * Capture the source as an image and store it on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null, array $options = []): ImageResult
    {
        return $this->capture()->store($path, $disk, $options);
    }

    /**
     * Capture an image using the configured image defaults.
     */
    public function capture(): ImageResult
    {
        return (new PendingImage(
            $this->capture,
            $this->newCaptureOptions($this->image),
        ))->capture();
    }

    /**
     * Combine the source's browser settings and selector with the output options.
     *
     * @template TOutput of ImageOptions|PdfOptions
     *
     * @param  TOutput  $output
     * @return CaptureOptions<TOutput>
     */
    protected function newCaptureOptions(ImageOptions|PdfOptions $output): CaptureOptions
    {
        return new CaptureOptions($this->browser, $output, $this->element);
    }
}
