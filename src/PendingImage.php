<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Concerns\AppliesConfiguration;
use DirectoryTree\Screenshot\Concerns\ConfiguresBrowser;
use DirectoryTree\Screenshot\Contracts\Driver;
use LogicException;

class PendingImage
{
    use AppliesConfiguration, ConfiguresBrowser;

    /**
     * Create a new pending screenshot.
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected string $type,
        protected ImageOptions $options,
        protected array $configureUsing = [],
    ) {
        if ($options->getFormat() === OutputFormat::Pdf) {
            throw new LogicException('Use pdf() to prepare a PDF capture.');
        }

        $this->defaults = clone $options;
        $this->options = clone $options;
    }

    /**
     * Set the browser viewport size in pixels.
     */
    public function size(int $width, int $height): static
    {
        return $this->configureOptions(static fn (ImageOptions $options) => $options->setSize($width, $height));
    }

    /**
     * Limit the captured dimensions in pixels.
     */
    public function maxSize(int $width, int $height): static
    {
        return $this->configureOptions(static fn (ImageOptions $options) => $options->setMaxSize($width, $height));
    }

    /**
     * Capture the full document, subject to the maximum dimensions.
     */
    public function fullPage(bool $value = true): static
    {
        return $this->configureOptions(static fn (ImageOptions $options) => $options->setFullPage($value));
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
        return $this->configureOptions(static fn (ImageOptions $options) => $options->setFormat(OutputFormat::Png));
    }

    /**
     * Capture a JPEG image, using configured quality unless explicitly supplied.
     */
    public function jpeg(?int $quality = null): static
    {
        return $this->configureOptions(static fn (ImageOptions $options) => $options->setFormat(OutputFormat::Jpeg, $quality));
    }

    /**
     * Prepare a PDF using a snapshot of this capture's browser settings.
     */
    public function pdf(): PendingPdf
    {
        return new PendingPdf(
            $this->driver, $this->source, $this->type, $this->defaults,
            configureUsing: $this->configureUsing, overrides: $this->overrides,
        );
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
        $capture = $this->configured();
        $capture->options->validate();

        return $this->driver->capture($this->source, $this->type, clone $capture->options);
    }
}
