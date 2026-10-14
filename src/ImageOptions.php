<?php

namespace DirectoryTree\Screenshot;

use InvalidArgumentException;

class ImageOptions extends BrowserOptions
{
    /**
     * Create a new set of capture options. Timeouts are in milliseconds.
     */
    public function __construct(
        int $width = 1024,
        int $height = 768,
        protected int $maxWidth = 2048,
        protected int $maxHeight = 10000,
        protected OutputFormat $format = OutputFormat::Png,
        protected int $quality = 80,
        int $browserTimeout = 60000,
        int $loadTimeout = 15000,
        protected bool $fullPage = true,
        bool $javascript = false,
        bool $failOnTimeout = false,
        ?string $socket = null,
    ) {
        parent::__construct($width, $height, $browserTimeout, $loadTimeout, $javascript, $failOnTimeout, $socket);
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
        parent::validate();

        $this->validateFormat($this->format);
        $this->validateDimensions($this->maxWidth, $this->maxHeight);
        $this->validateQuality($this->quality);
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
