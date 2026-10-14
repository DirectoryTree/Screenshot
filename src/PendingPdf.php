<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Enums\PaperSize;

/**
 * @extends PendingCapture<PdfOptions, PdfResult>
 */
class PendingPdf extends PendingCapture
{
    /**
     * Select a standard paper size.
     */
    public function paper(PaperSize $size): static
    {
        $this->options->output->setPaper($size);

        return $this;
    }

    /**
     * Set the paper dimensions in millimeters, before applying orientation.
     */
    public function paperSize(float $width, float $height): static
    {
        $this->options->output->setPaperSize($width, $height);

        return $this;
    }

    /**
     * Print in landscape orientation.
     */
    public function landscape(bool $value = true): static
    {
        $this->options->output->setLandscape($value);

        return $this;
    }

    /**
     * Set page margins in millimeters.
     */
    public function margins(float $top = 10, float $right = 10, float $bottom = 10, float $left = 10): static
    {
        $this->options->output->setMargins($top, $right, $bottom, $left);

        return $this;
    }

    /**
     * Include background colors and images when printing.
     */
    public function background(bool $value = true): static
    {
        $this->options->output->setBackground($value);

        return $this;
    }

    /**
     * Prefer the page size declared by CSS @page rules.
     */
    public function preferCssPageSize(bool $value = true): static
    {
        $this->options->output->setPreferCssPageSize($value);

        return $this;
    }
}
