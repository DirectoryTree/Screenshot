<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Concerns\ConfiguresBrowser;
use DirectoryTree\Screenshot\Contracts\Driver;

class PendingPdf
{
    use ConfiguresBrowser;

    /**
     * Create a pending PDF with independent browser and print options.
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected string $type,
        protected ImageOptions $options,
        protected PdfOptions $pdfOptions = new PdfOptions,
    ) {
        $this->options = (clone $options)->setFormat(OutputFormat::Pdf);
        $this->pdfOptions = clone $pdfOptions;
    }

    /**
     * Select a standard paper size: A3, A4, A5, Letter, or Legal.
     */
    public function paper(string $format): static
    {
        $this->pdfOptions->setPaper($format);

        return $this;
    }

    /**
     * Set the paper dimensions in millimeters, before applying orientation.
     */
    public function paperSize(float $width, float $height): static
    {
        $this->pdfOptions->setPaperSize($width, $height);

        return $this;
    }

    /**
     * Print in landscape orientation.
     */
    public function landscape(bool $value = true): static
    {
        $this->pdfOptions->setLandscape($value);

        return $this;
    }

    /**
     * Set page margins in millimeters.
     */
    public function margins(float $top = 10, float $right = 10, float $bottom = 10, float $left = 10): static
    {
        $this->pdfOptions->setMargins($top, $right, $bottom, $left);

        return $this;
    }

    /**
     * Include background colors and images when printing.
     */
    public function background(bool $value = true): static
    {
        $this->pdfOptions->setBackground($value);

        return $this;
    }

    /**
     * Prefer the page size declared by CSS @page rules.
     */
    public function preferCssPageSize(bool $value = true): static
    {
        $this->pdfOptions->setPreferCssPageSize($value);

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
        $this->options->validate();
        $this->pdfOptions->validate();

        return $this->driver->capture($this->source, $this->type, clone $this->options, clone $this->pdfOptions);
    }
}
