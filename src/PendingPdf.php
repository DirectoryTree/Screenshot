<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Concerns\AppliesConfiguration;
use DirectoryTree\Screenshot\Concerns\ConfiguresBrowser;
use DirectoryTree\Screenshot\Contracts\Driver;

class PendingPdf
{
    use AppliesConfiguration, ConfiguresBrowser;

    /**
     * The browser and print options for this capture.
     */
    protected PdfOptions $options;

    /**
     * Explicit print settings, applied together after format defaults.
     *
     * @var array{float, float}|null
     */
    protected ?array $paperOverride = null;

    /**
     * The explicitly selected margins.
     *
     * @var array{float, float, float, float}|null
     */
    protected ?array $marginsOverride = null;

    /**
     * The explicitly selected orientation.
     */
    protected ?bool $landscapeOverride = null;

    /**
     * Whether backgrounds were explicitly enabled or disabled.
     */
    protected ?bool $backgroundOverride = null;

    /**
     * Whether CSS page sizes were explicitly preferred.
     */
    protected ?bool $preferCssPageSizeOverride = null;

    /**
     * Create a pending PDF with a single set of browser and print options.
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected SourceType $type,
        ?PdfOptions $options = null,
        protected array $configureUsing = [],
        array $overrides = [],
    ) {
        $options ??= PdfOptions::make();

        $this->defaults = clone $options;
        $this->options = clone $options;
        $this->overrides = $overrides;

        foreach ($overrides as $override) {
            $override($this->options);
        }
    }

    /**
     * Select a standard paper size.
     */
    public function paper(PaperSize $size): static
    {
        $this->options->setPaper($size);
        $this->paperOverride = [$this->options->getPaperWidth(), $this->options->getPaperHeight()];

        return $this;
    }

    /**
     * Set the paper dimensions in millimeters, before applying orientation.
     */
    public function paperSize(float $width, float $height): static
    {
        $this->options->setPaperSize($width, $height);
        $this->paperOverride = [$width, $height];

        return $this;
    }

    /**
     * Print in landscape orientation.
     */
    public function landscape(bool $value = true): static
    {
        $this->options->setLandscape($value);
        $this->landscapeOverride = $value;

        return $this;
    }

    /**
     * Set page margins in millimeters.
     */
    public function margins(float $top = 10, float $right = 10, float $bottom = 10, float $left = 10): static
    {
        $this->options->setMargins($top, $right, $bottom, $left);
        $this->marginsOverride = [$top, $right, $bottom, $left];

        return $this;
    }

    /**
     * Include background colors and images when printing.
     */
    public function background(bool $value = true): static
    {
        $this->options->setBackground($value);
        $this->backgroundOverride = $value;

        return $this;
    }

    /**
     * Prefer the page size declared by CSS @page rules.
     */
    public function preferCssPageSize(bool $value = true): static
    {
        $this->options->setPreferCssPageSize($value);
        $this->preferCssPageSizeOverride = $value;

        return $this;
    }

    /**
     * Generate the PDF and save it to a local path.
     */
    public function save(string $path): PdfResult
    {
        return $this->capture()->save($path);
    }

    /**
     * Generate the PDF and store it on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null, array $options = []): PdfResult
    {
        return $this->capture()->store($path, $disk, $options);
    }

    /**
     * Generate the PDF.
     */
    public function capture(): PdfResult
    {
        $capture = $this->configured();
        $capture->options->validate();

        return $this->driver->capture($this->source, $this->type, clone $capture->options);
    }

    /**
     * Merge explicit print settings and validate the complete final layout.
     */
    protected function configuredOutputOptions(PdfOptions $defaults): PdfOptions
    {
        [$width, $height] = $this->paperOverride ?? [$defaults->getPaperWidth(), $defaults->getPaperHeight()];
        [$top, $right, $bottom, $left] = $this->marginsOverride ?? array_values($defaults->getMargins());

        return (clone $defaults)
            ->setLayout(
                width: $width, height: $height,
                landscape: $this->landscapeOverride ?? $defaults->getLandscape(),
                top: $top, right: $right, bottom: $bottom, left: $left,
            )
            ->setBackground($this->backgroundOverride ?? $defaults->getBackground())
            ->setPreferCssPageSize($this->preferCssPageSizeOverride ?? $defaults->getPreferCssPageSize());
    }
}
