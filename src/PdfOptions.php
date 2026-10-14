<?php

namespace DirectoryTree\Screenshot;

use InvalidArgumentException;

class PdfOptions extends BrowserOptions
{
    /**
     * Create print options. Paper dimensions and margins are in millimeters.
     */
    public function __construct(
        protected float $paperWidth = 210,
        protected float $paperHeight = 297,
        protected float $marginTop = 10,
        protected float $marginRight = 10,
        protected float $marginBottom = 10,
        protected float $marginLeft = 10,
        protected bool $landscape = false,
        protected bool $background = true,
        protected bool $preferCssPageSize = false,
        int $width = 1024,
        int $height = 768,
        int $browserTimeout = 60000,
        int $loadTimeout = 15000,
        bool $javascript = false,
        bool $failOnTimeout = false,
        ?string $socket = null,
    ) {
        parent::__construct($width, $height, $browserTimeout, $loadTimeout, $javascript, $failOnTimeout, $socket);
    }

    /**
     * Create PDF options from shared browser settings.
     */
    public static function fromBrowserOptions(BrowserOptions $options): static
    {
        return new static(
            width: $options->getWidth(), height: $options->getHeight(),
            browserTimeout: $options->getBrowserTimeout(), loadTimeout: $options->getLoadTimeout(),
            javascript: $options->getJavascript(), failOnTimeout: $options->getFailOnTimeout(),
            socket: $options->getSocket(),
        );
    }

    /**
     * Get the output format.
     */
    public function getFormat(): OutputFormat
    {
        return OutputFormat::Pdf;
    }

    /**
     * Get the paper width in millimeters, before applying orientation.
     */
    public function getPaperWidth(): float
    {
        return $this->paperWidth;
    }

    /**
     * Get the paper height in millimeters, before applying orientation.
     */
    public function getPaperHeight(): float
    {
        return $this->paperHeight;
    }

    /**
     * Select a standard paper size.
     */
    public function setPaper(PaperSize $size): static
    {
        [$width, $height] = $size->dimensions();

        return $this->setPaperSize($width, $height);
    }

    /**
     * Set paper dimensions after validating the printable area.
     */
    public function setPaperSize(float $width, float $height): static
    {
        $this->validateLayout($width, $height, $this->landscape, $this->marginTop, $this->marginRight, $this->marginBottom, $this->marginLeft);

        $this->paperWidth = $width;
        $this->paperHeight = $height;

        return $this;
    }

    /**
     * Get page margins in millimeters.
     *
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    public function getMargins(): array
    {
        return ['top' => $this->marginTop, 'right' => $this->marginRight, 'bottom' => $this->marginBottom, 'left' => $this->marginLeft];
    }

    /**
     * Set all page margins after validating the printable area.
     */
    public function setMargins(float $top, float $right, float $bottom, float $left): static
    {
        $this->validateLayout($this->paperWidth, $this->paperHeight, $this->landscape, $top, $right, $bottom, $left);

        $this->marginTop = $top;
        $this->marginRight = $right;
        $this->marginBottom = $bottom;
        $this->marginLeft = $left;

        return $this;
    }

    /**
     * Determine whether landscape orientation is enabled.
     */
    public function getLandscape(): bool
    {
        return $this->landscape;
    }

    /**
     * Set the orientation after validating the printable area.
     */
    public function setLandscape(bool $value): static
    {
        $this->validateLayout($this->paperWidth, $this->paperHeight, $value, $this->marginTop, $this->marginRight, $this->marginBottom, $this->marginLeft);

        $this->landscape = $value;

        return $this;
    }

    /**
     * Determine whether backgrounds are printed.
     */
    public function getBackground(): bool
    {
        return $this->background;
    }

    /**
     * Set whether backgrounds are printed.
     */
    public function setBackground(bool $value): static
    {
        $this->background = $value;

        return $this;
    }

    /**
     * Determine whether CSS @page sizes take precedence.
     */
    public function getPreferCssPageSize(): bool
    {
        return $this->preferCssPageSize;
    }

    /**
     * Set whether CSS @page sizes take precedence.
     */
    public function setPreferCssPageSize(bool $value): static
    {
        $this->preferCssPageSize = $value;

        return $this;
    }

    /**
     * Validate the complete print configuration.
     */
    public function validate(): void
    {
        parent::validate();

        $this->validateLayout($this->paperWidth, $this->paperHeight, $this->landscape, $this->marginTop, $this->marginRight, $this->marginBottom, $this->marginLeft);
    }

    /**
     * Convert millimeters to Chrome's print options in inches.
     */
    public function toArray(): array
    {
        return [
            'paperWidth' => $this->paperWidth / 25.4,
            'paperHeight' => $this->paperHeight / 25.4,
            'marginTop' => $this->marginTop / 25.4,
            'marginRight' => $this->marginRight / 25.4,
            'marginBottom' => $this->marginBottom / 25.4,
            'marginLeft' => $this->marginLeft / 25.4,
            'landscape' => $this->landscape,
            'printBackground' => $this->background,
            'preferCSSPageSize' => $this->preferCssPageSize,
        ];
    }

    /**
     * Require finite dimensions and margins that leave a printable area.
     */
    protected function validateLayout(float $width, float $height, bool $landscape, float $top, float $right, float $bottom, float $left): void
    {
        foreach ([$width, $height] as $dimension) {
            if (! is_finite($dimension) || $dimension <= 0) {
                throw new InvalidArgumentException('Paper dimensions must be finite and greater than zero millimeters.');
            }
        }

        foreach ([$top, $right, $bottom, $left] as $margin) {
            if (! is_finite($margin) || $margin < 0) {
                throw new InvalidArgumentException('Page margins must be finite and at least zero millimeters.');
            }
        }

        if ($left + $right >= ($landscape ? $height : $width) || $top + $bottom >= ($landscape ? $width : $height)) {
            throw new InvalidArgumentException('Page margins must leave a positive printable area.');
        }
    }
}
