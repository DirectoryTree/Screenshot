<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;

class PendingScreenshot
{
    /**
     * Create a new pending screenshot.
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected string $type,
        protected CaptureOptions $options,
    ) {
        $this->options = clone $options;
    }

    /**
     * Set the browser viewport size in pixels.
     */
    public function size(int $width, int $height): static
    {
        $this->options->setSize($width, $height);

        return $this;
    }

    /**
     * Limit the captured dimensions in pixels.
     */
    public function maxSize(int $width, int $height): static
    {
        $this->options->setMaxSize($width, $height);

        return $this;
    }

    /**
     * Capture the full document, subject to the maximum dimensions.
     */
    public function fullPage(bool $value = true): static
    {
        $this->options->setFullPage($value);

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
        $this->options->setFormat('png');

        return $this;
    }

    /**
     * Capture a JPEG image with the given quality.
     */
    public function jpeg(int $quality = 80): static
    {
        $this->options->setFormat('jpeg', $quality);

        return $this;
    }

    /**
     * Set the timeout for individual browser operations in milliseconds.
     */
    public function timeout(int $milliseconds): static
    {
        $this->options->setTimeout($milliseconds);

        return $this;
    }

    /**
     * Set the page load timeout in milliseconds.
     */
    public function loadTimeout(int $milliseconds): static
    {
        $this->options->setLoadTimeout($milliseconds);

        return $this;
    }

    /**
     * Allow JavaScript execution in the captured document.
     */
    public function javascript(bool $value = true): static
    {
        $this->options->setJavascript($value);

        return $this;
    }

    /**
     * Throw when page loading times out instead of capturing partial content.
     */
    public function failOnTimeout(bool $value = true): static
    {
        $this->options->setFailOnTimeout($value);

        return $this;
    }

    /**
     * Share a browser with other workers using the same key.
     */
    public function socket(string $key): static
    {
        $this->options->setSocket($key);

        return $this;
    }

    /**
     * Use a dedicated browser for this capture.
     */
    public function withoutSocket(): static
    {
        $this->options->setSocket(null);

        return $this;
    }

    /**
     * Capture the screenshot.
     */
    public function capture(): ScreenshotResult
    {
        $this->options->validate();

        return $this->driver->capture($this->source, $this->type, clone $this->options);
    }

    /**
     * Capture the screenshot and save it to a local path.
     */
    public function save(string $path): ScreenshotResult
    {
        return $this->capture()->save($path);
    }

    /**
     * Capture the screenshot and store it on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null): ScreenshotResult
    {
        return $this->capture()->store($path, $disk);
    }
}
