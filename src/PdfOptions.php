<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
use InvalidArgumentException;

class PdfOptions
{
    /**
     * The paper width in millimeters, before orientation.
     */
    protected float $paperWidth = 210;

    /**
     * The paper height in millimeters, before orientation.
     */
    protected float $paperHeight = 297;

    /**
     * The top page margin in millimeters.
     */
    protected float $marginTop = 10;

    /**
     * The right page margin in millimeters.
     */
    protected float $marginRight = 10;

    /**
     * The bottom page margin in millimeters.
     */
    protected float $marginBottom = 10;

    /**
     * The left page margin in millimeters.
     */
    protected float $marginLeft = 10;

    /**
     * Whether landscape orientation is enabled.
     */
    protected bool $landscape = false;

    /**
     * Whether background colors and images are printed.
     */
    protected bool $background = true;

    /**
     * Whether CSS @page sizes take precedence.
     */
    protected bool $preferCssPageSize = false;

    /**
     * Create options using the declared defaults.
     */
    public function __construct()
    {
        $this->validate();
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
        return $this->setLayout(width: $width, height: $height);
    }

    /**
     * Get page margins in millimeters.
     *
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    public function getMargins(): array
    {
        return [
            'top' => $this->marginTop,
            'right' => $this->marginRight,
            'bottom' => $this->marginBottom,
            'left' => $this->marginLeft,
        ];
    }

    /**
     * Set all page margins after validating the printable area.
     */
    public function setMargins(float $top, float $right, float $bottom, float $left): static
    {
        return $this->setLayout(top: $top, right: $right, bottom: $bottom, left: $left);
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
        return $this->setLayout(landscape: $value);
    }

    /**
     * Validate and apply paper, orientation, and margins together.
     * Omitted values retain their current settings.
     */
    public function setLayout(
        ?float $width = null,
        ?float $height = null,
        ?bool $landscape = null,
        ?float $top = null,
        ?float $right = null,
        ?float $bottom = null,
        ?float $left = null,
    ): static {
        $this->validateLayout(
            $width ?? $this->paperWidth, $height ?? $this->paperHeight,
            $landscape ?? $this->landscape,
            $top ?? $this->marginTop, $right ?? $this->marginRight,
            $bottom ?? $this->marginBottom, $left ?? $this->marginLeft,
        );

        $this->paperWidth = $width ?? $this->paperWidth;
        $this->paperHeight = $height ?? $this->paperHeight;
        $this->landscape = $landscape ?? $this->landscape;
        $this->marginTop = $top ?? $this->marginTop;
        $this->marginRight = $right ?? $this->marginRight;
        $this->marginBottom = $bottom ?? $this->marginBottom;
        $this->marginLeft = $left ?? $this->marginLeft;

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
