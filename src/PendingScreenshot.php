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
    ) {}

    /**
     * Set the browser viewport size in pixels.
     */
    public function size(int $width, int $height): static
    {
        return $this->withOptions(['width' => $width, 'height' => $height]);
    }

    /**
     * Limit the captured dimensions in pixels.
     */
    public function maxSize(int $width, int $height): static
    {
        return $this->withOptions(['maxWidth' => $width, 'maxHeight' => $height]);
    }

    /**
     * Capture the full document, subject to the maximum dimensions.
     */
    public function fullPage(bool $value = true): static
    {
        return $this->withOptions(['fullPage' => $value]);
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
        return $this->withOptions(['format' => 'png']);
    }

    /**
     * Capture a JPEG image with the given quality.
     */
    public function jpeg(int $quality = 80): static
    {
        return $this->withOptions(['format' => 'jpeg', 'quality' => $quality]);
    }

    /**
     * Set the timeout for individual browser operations in milliseconds.
     */
    public function timeout(int $milliseconds): static
    {
        return $this->withOptions(['timeout' => $milliseconds]);
    }

    /**
     * Set the page load timeout in milliseconds.
     */
    public function loadTimeout(int $milliseconds): static
    {
        return $this->withOptions(['loadTimeout' => $milliseconds]);
    }

    /**
     * Allow JavaScript execution in the captured document.
     */
    public function javascript(bool $value = true): static
    {
        return $this->withOptions(['javascript' => $value]);
    }

    /**
     * Throw when page loading times out instead of capturing partial content.
     */
    public function failOnTimeout(bool $value = true): static
    {
        return $this->withOptions(['failOnTimeout' => $value]);
    }

    /**
     * Share a browser with other workers using the same key.
     */
    public function socket(string $key): static
    {
        return $this->withOptions(['socket' => $key, 'socketPath' => null]);
    }

    /**
     * Share a browser using a file containing its WebSocket address.
     */
    public function socketPath(string $path): static
    {
        return $this->withOptions(['socket' => null, 'socketPath' => $path]);
    }

    /**
     * Use a dedicated browser for this capture.
     */
    public function withoutSocket(): static
    {
        return $this->withOptions(['socket' => null, 'socketPath' => null]);
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

    /**
     * Apply validated options without leaving partially updated state.
     */
    protected function withOptions(array $values): static
    {
        $options = clone $this->options;

        foreach ($values as $key => $value) {
            $options->{$key} = $value;
        }

        $options->validate();

        $this->options = $options;

        return $this;
    }
}
