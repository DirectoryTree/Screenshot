<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Concerns\AppliesConfiguration;
use DirectoryTree\Screenshot\Concerns\ConfiguresBrowser;
use DirectoryTree\Screenshot\Contracts\Driver;

class PendingPdf
{
    use AppliesConfiguration, ConfiguresBrowser;

    /**
     * The print options before caller-specific changes.
     */
    protected PdfOptions $pdfDefaults;

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
     * Create a pending PDF with independent browser and print options.
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected string $type,
        protected ImageOptions $options,
        protected PdfOptions $pdfOptions = new PdfOptions,
        protected array $configureUsing = [],
        array $overrides = [],
    ) {
        $this->defaults = clone $options;
        $this->pdfDefaults = clone $pdfOptions;
        $this->options = clone $options;
        $this->pdfOptions = clone $pdfOptions;
        $this->overrides = $overrides;

        foreach ($overrides as $override) {
            $override($this->options);
        }

        $this->options->setFormat(OutputFormat::Pdf);
    }

    /**
     * Select a standard paper size: A3, A4, A5, Letter, or Legal.
     */
    public function paper(string $format): static
    {
        $this->pdfOptions->setPaper($format);
        $this->paperOverride = [$this->pdfOptions->getPaperWidth(), $this->pdfOptions->getPaperHeight()];

        return $this;
    }

    /**
     * Set the paper dimensions in millimeters, before applying orientation.
     */
    public function paperSize(float $width, float $height): static
    {
        $this->pdfOptions->setPaperSize($width, $height);
        $this->paperOverride = [$width, $height];

        return $this;
    }

    /**
     * Print in landscape orientation.
     */
    public function landscape(bool $value = true): static
    {
        $this->pdfOptions->setLandscape($value);
        $this->landscapeOverride = $value;

        return $this;
    }

    /**
     * Set page margins in millimeters.
     */
    public function margins(float $top = 10, float $right = 10, float $bottom = 10, float $left = 10): static
    {
        $this->pdfOptions->setMargins($top, $right, $bottom, $left);
        $this->marginsOverride = [$top, $right, $bottom, $left];

        return $this;
    }

    /**
     * Include background colors and images when printing.
     */
    public function background(bool $value = true): static
    {
        $this->pdfOptions->setBackground($value);
        $this->backgroundOverride = $value;

        return $this;
    }

    /**
     * Prefer the page size declared by CSS @page rules.
     */
    public function preferCssPageSize(bool $value = true): static
    {
        $this->pdfOptions->setPreferCssPageSize($value);
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
        $capture->pdfOptions->validate();

        return $this->driver->capture($this->source, $this->type, clone $capture->options, clone $capture->pdfOptions);
    }

    /**
     * Merge explicit print settings and validate the complete final layout.
     */
    protected function configuredPrintOptions(PdfOptions $defaults): PdfOptions
    {
        [$width, $height] = $this->paperOverride ?? [$defaults->getPaperWidth(), $defaults->getPaperHeight()];
        [$top, $right, $bottom, $left] = $this->marginsOverride ?? array_values($defaults->getMargins());

        return new PdfOptions(
            paperWidth: $width, paperHeight: $height,
            marginTop: $top, marginRight: $right, marginBottom: $bottom, marginLeft: $left,
            landscape: $this->landscapeOverride ?? $defaults->getLandscape(),
            background: $this->backgroundOverride ?? $defaults->getBackground(),
            preferCssPageSize: $this->preferCssPageSizeOverride ?? $defaults->getPreferCssPageSize(),
        );
    }
}
