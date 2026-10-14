<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Concerns\ConfiguresBrowser;
use DirectoryTree\Screenshot\Contracts\Driver;
use LogicException;

class PendingImage
{
    use ConfiguresBrowser;

    /**
     * Create a new pending screenshot.
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected string $type,
        protected ImageOptions $options,
    ) {
        if ($options->getFormat() === OutputFormat::Pdf) {
            throw new LogicException('Use pdf() to prepare a PDF capture.');
        }

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
        $this->options->setFormat(OutputFormat::Png);

        return $this;
    }

    /**
     * Capture a JPEG image with the given quality.
     */
    public function jpeg(int $quality = 80): static
    {
        $this->options->setFormat(OutputFormat::Jpeg, $quality);

        return $this;
    }

    /**
     * Prepare a PDF using a snapshot of this capture's browser settings.
     */
    public function pdf(): PendingPdf
    {
        return new PendingPdf($this->driver, $this->source, $this->type, $this->options);
    }

    /**
     * Capture the screenshot and save it to a local path.
     */
    public function save(string $path): ImageResult
    {
        return $this->capture()->save($path);
    }

    /**
     * Capture the screenshot and store it on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null, array $options = []): ImageResult
    {
        return $this->capture()->store($path, $disk, $options);
    }

    /**
     * Capture the screenshot.
     */
    public function capture(): ImageResult
    {
        $this->options->validate();

        return $this->driver->capture($this->source, $this->type, clone $this->options);
    }
}
