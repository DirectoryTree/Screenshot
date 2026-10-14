<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Enums\OutputFormat;
use InvalidArgumentException;

class ImageOptions extends BrowserOptions
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
        $this->specified['maxSize'] = true;

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
            $this->specified['quality'] = true;
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
        $this->specified['fullPage'] = true;

        return $this;
    }

    /**
     * Reject unsupported options before starting a browser.
     */
    public function validate(): void
    {
        parent::validate();

        $this->validateFormat($this->format);
        $this->validateDimensions($this->maxWidth, $this->maxHeight);
        $this->validateQuality($this->quality);
    }

    /**
     * Merge image defaults without changing the selected format or explicit values.
     *
     * @param  ImageOptions  $defaults
     */
    protected function mergeDefaults(BrowserOptions $defaults): void
    {
        parent::mergeDefaults($defaults);

        if (! isset($this->specified['maxSize'])) {
            $this->maxWidth = $defaults->maxWidth;
            $this->maxHeight = $defaults->maxHeight;
        }

        $this->quality = isset($this->specified['quality']) ? $this->quality : $defaults->quality;
        $this->fullPage = isset($this->specified['fullPage']) ? $this->fullPage : $defaults->fullPage;
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
