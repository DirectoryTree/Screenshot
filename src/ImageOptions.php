<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Enums\OutputFormat;
use InvalidArgumentException;

class ImageOptions
{
    /**
     * The maximum captured width in pixels.
     */
    protected int $maxWidth = 2048;

    /**
     * The maximum captured height in pixels.
     */
    protected int $maxHeight = 10000;

    /**
     * The image output format.
     */
    protected OutputFormat $format = OutputFormat::Png;

    /**
     * The JPEG quality.
     */
    protected int $quality = 80;

    /**
     * Whether to capture the full document.
     */
    protected bool $fullPage = true;

    /**
     * Create options using the declared defaults.
     */
    public function __construct()
    {
        $this->validate();
    }

    /**
     * Get the maximum captured width in pixels.
     */
    public function getMaxWidth(): int
    {
        return $this->maxWidth;
    }

    /**
     * Get the maximum captured height in pixels.
     */
    public function getMaxHeight(): int
    {
        return $this->maxHeight;
    }

    /**
     * Set the capture limits after validating both dimensions.
     */
    public function setMaxSize(int $width, int $height): static
    {
        $this->validateDimensions($width, $height);

        $this->maxWidth = $width;
        $this->maxHeight = $height;

        return $this;
    }

    /**
     * Get the output format.
     */
    public function getFormat(): OutputFormat
    {
        return $this->format;
    }

    /**
     * Get the JPEG quality.
     */
    public function getQuality(): int
    {
        return $this->quality;
    }

    /**
     * Set the output format and optional JPEG quality atomically.
     */
    public function setFormat(OutputFormat $format, ?int $quality = null): static
    {
        $this->validateFormat($format);

        if ($quality !== null) {
            $this->validateQuality($quality);
        }

        $this->format = $format;

        if ($quality !== null) {
            $this->quality = $quality;
        }

        return $this;
    }

    /**
     * Determine whether to capture the full document.
     */
    public function getFullPage(): bool
    {
        return $this->fullPage;
    }

    /**
     * Set whether to capture the full document.
     */
    public function setFullPage(bool $value): static
    {
        $this->fullPage = $value;

        return $this;
    }

    /**
     * Reject unsupported options before starting a browser.
     */
    public function validate(): void
    {
        $this->validateFormat($this->format);
        $this->validateDimensions($this->maxWidth, $this->maxHeight);
        $this->validateQuality($this->quality);
    }

    /**
     * Validate capture limits before applying either dimension.
     */
    protected function validateDimensions(int $width, int $height): void
    {
        if ($width < 1 || $height < 1 || $width > 16384 || $height > 16384 || $width * $height > 40000000) {
            throw new InvalidArgumentException('Dimensions must be between 1 and 16384 pixels and no more than 40 million pixels in total.');
        }
    }

    /**
     * Validate the JPEG quality before selecting the format.
     */
    protected function validateQuality(int $quality): void
    {
        if ($quality < 0 || $quality > 100) {
            throw new InvalidArgumentException('JPEG quality must be between 0 and 100.');
        }
    }

    /**
     * Require an image output format.
     */
    protected function validateFormat(OutputFormat $format): void
    {
        if ($format === OutputFormat::Pdf) {
            throw new InvalidArgumentException('Image options require PNG or JPEG output.');
        }
    }
}
