<?php

namespace DirectoryTree\Screenshot;

class PendingSource
{
    /**
     * Prepare a source with an independent snapshot of its browser settings.
     */
    public function __construct(
        protected Screenshot $factory,
        protected PendingCapture $capture,
        protected BrowserOptions $browser,
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
        return $this->factory->newPendingImage($this->capture, $this->browser)->png();
    }

    /**
     * Prepare a JPEG image with the configured or supplied quality.
     */
    public function jpeg(?int $quality = null): PendingImage
    {
        return $this->factory->newPendingImage($this->capture, $this->browser)->jpeg($quality);
    }

    /**
     * Prepare a PDF using this source's browser settings.
     */
    public function pdf(): PendingPdf
    {
        return $this->factory->newPendingPdf($this->capture, $this->browser);
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
        return $this->factory->newPendingImage($this->capture, $this->browser)->capture();
    }
}
