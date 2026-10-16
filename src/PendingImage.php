<?php

namespace DirectoryTree\Screenshot;

use Closure;
use DirectoryTree\Screenshot\Enums\OutputFormat;

class PendingImage
{
    /**
     * Customize the driver immediately before capturing.
     */
    public function before(Closure $callback): static
    {
        $this->options->before($callback);

        return $this;
    }

    /**
     * Create an image builder with independent settings.
     *
     * @param  CaptureOptions<ImageOptions>  $options
     */
    public function __construct(
        protected PendingCapture $capture,
        protected CaptureOptions $options,
    ) {
        $this->options = clone $options;
    }

    /**
     * Set the viewport dimensions after validating both values.
     */
    public function size(int $width, int $height): static
    {
        $this->options->browser()->setSize($width, $height);

        return $this;
    }

    /**
     * Set page-load and individual browser-command timeouts in milliseconds.
     */
    public function timeouts(?int $load = null, ?int $browser = null): static
    {
        $this->options->browser()->setTimeouts($load, $browser);

        return $this;
    }

    /**
     * Allow JavaScript execution in the captured document.
     */
    public function javascript(bool $value = true): static
    {
        $this->options->browser()->setJavascript($value);

        return $this;
    }

    /**
     * Throw when page loading times out instead of capturing partial content.
     */
    public function failOnTimeout(bool $value = true): static
    {
        $this->options->browser()->setFailOnTimeout($value);

        return $this;
    }

    /**
     * Share a browser with other workers using the same key.
     */
    public function socket(string $key): static
    {
        $this->options->browser()->setSocket($key);

        return $this;
    }

    /**
     * Use a dedicated browser for this capture.
     */
    public function withoutSocket(): static
    {
        $this->options->browser()->setSocket(null);

        return $this;
    }

    /**
     * Limit the captured dimensions in pixels.
     */
    public function maxSize(int $width, int $height): static
    {
        $this->options->output()->setMaxSize($width, $height);

        return $this;
    }

    /**
     * Capture the full document, subject to the maximum dimensions.
     */
    public function fullPage(bool $value = true): static
    {
        $this->options->output()->setFullPage($value);

        return $this;
    }

    /**
     * Capture only the visible viewport.
     */
    public function viewport(): static
    {
        return $this->fullPage(false);
    }

    /**
     * Capture a PNG image.
     */
    public function png(): static
    {
        $this->options->output()->setFormat(OutputFormat::Png);

        return $this;
    }

    /**
     * Capture a JPEG image, using configured quality unless explicitly supplied.
     */
    public function jpeg(?int $quality = null): static
    {
        $this->options->output()->setFormat(OutputFormat::Jpeg, $quality);

        return $this;
    }

    /**
     * Capture the document and save it to a local path.
     */
    public function save(string $path): ImageResult
    {
        return $this->capture()->save($path);
    }

    /**
     * Capture the document and store it on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null, array $options = []): ImageResult
    {
        return $this->capture()->store($path, $disk, $options);
    }

    /**
     * Capture the document using its current settings.
     */
    public function capture(): ImageResult
    {
        return $this->capture->capture($this->options);
    }
}
