<?php

namespace DirectoryTree\Screenshot;

/**
 * @extends PendingCapture<ImageOptions, ImageResult>
 */
class PendingImage extends PendingCapture
{
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
     * Capture a JPEG image, using configured quality unless explicitly supplied.
     */
    public function jpeg(?int $quality = null): static
    {
        $this->options->setFormat(OutputFormat::Jpeg, $quality);

        return $this;
    }

    /**
     * Prepare a PDF using a snapshot of this capture's browser settings.
     */
    public function pdf(): PendingPdf
    {
        return new PendingPdf(
            $this->driver, $this->source, $this->type,
            PdfOptions::fromBrowserOptions($this->options),
            $this->configureUsing, PdfOptions::fromBrowserOptions($this->defaults),
        );
    }
}
