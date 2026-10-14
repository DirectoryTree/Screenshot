<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;

/**
 * @extends PendingCapture<ImageOptions, ImageResult>
 */
class PendingImage extends PendingCapture
{
    /**
     * The print defaults for captures converted to PDF.
     */
    protected PdfOptions $pdf;

    /**
     * Create an image capture with a snapshot of the PDF defaults.
     *
     * @param  CaptureOptions<ImageOptions>  $options
     */
    public function __construct(
        Driver $driver,
        string $source,
        SourceType $type,
        CaptureOptions $options,
        ?PdfOptions $pdf = null,
    ) {
        parent::__construct($driver, $source, $type, $options);

        $this->pdf = $pdf ? clone $pdf : PdfOptions::make();
    }

    /**
     * Limit the captured dimensions in pixels.
     */
    public function maxSize(int $width, int $height): static
    {
        $this->options->output->setMaxSize($width, $height);

        return $this;
    }

    /**
     * Capture the full document, subject to the maximum dimensions.
     */
    public function fullPage(bool $value = true): static
    {
        $this->options->output->setFullPage($value);

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
        $this->options->output->setFormat(OutputFormat::Png);

        return $this;
    }

    /**
     * Capture a JPEG image, using configured quality unless explicitly supplied.
     */
    public function jpeg(?int $quality = null): static
    {
        $this->options->output->setFormat(OutputFormat::Jpeg, $quality);

        return $this;
    }

    /**
     * Prepare a PDF using a snapshot of this capture's browser settings.
     */
    public function pdf(): PendingPdf
    {
        return new PendingPdf(
            $this->driver, $this->source, $this->type,
            new CaptureOptions($this->options->browser, $this->pdf),
        );
    }
}
