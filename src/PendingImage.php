<?php

namespace DirectoryTree\Screenshot;

use Closure;
use DirectoryTree\Screenshot\Concerns\AppliesConfiguration;
use DirectoryTree\Screenshot\Concerns\ConfiguresBrowser;
use DirectoryTree\Screenshot\Contracts\Driver;

class PendingImage
{
    use AppliesConfiguration, ConfiguresBrowser;

    /**
     * Explicit image settings, independent of shared browser settings.
     *
     * @var list<Closure(ImageOptions): mixed>
     */
    protected array $imageOverrides = [];

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
        $this->defaults = clone $options;
        $this->options = clone $options;
    }

    /**
     * Limit the captured dimensions in pixels.
     */
    public function maxSize(int $width, int $height): static
    {
        return $this->configureImageOptions(static fn (ImageOptions $options) => $options->setMaxSize($width, $height));
    }

    /**
     * Capture the full document, subject to the maximum dimensions.
     */
    public function fullPage(bool $value = true): static
    {
        return $this->configureImageOptions(static fn (ImageOptions $options) => $options->setFullPage($value));
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
        return $this->configureImageOptions(static fn (ImageOptions $options) => $options->setFormat(OutputFormat::Png));
    }

    /**
     * Capture a JPEG image, using configured quality unless explicitly supplied.
     */
    public function jpeg(?int $quality = null): static
    {
        return $this->configureImageOptions(static fn (ImageOptions $options) => $options->setFormat(OutputFormat::Jpeg, $quality));
    }

    /**
     * Prepare a PDF using a snapshot of this capture's browser settings.
     */
    public function pdf(): PendingPdf
    {
        return new PendingPdf(
            $this->driver, $this->source, $this->type, PdfOptions::fromBrowserOptions($this->defaults),
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

    /**
     * Validate and remember an explicit image option change.
     */
    protected function configureImageOptions(Closure $callback): static
    {
        $callback($this->options);
        $this->imageOverrides[] = $callback;

        return $this;
    }

    /**
     * Apply explicit image settings after the selected format's defaults.
     */
    protected function configuredOutputOptions(ImageOptions $options): ImageOptions
    {
        foreach ($this->imageOverrides as $override) {
            $override($options);
        }

        return $options;
    }
}
