<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\PdfDriver;
use LogicException;

class PendingScreenshot
{
    /**
     * Create a new pending screenshot.
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected string $type,
        protected ImageOptions $options,
        protected PdfOptions $pdfOptions = new PdfOptions,
    ) {
        $this->options = clone $options;
        $this->pdfOptions = clone $pdfOptions;
    }

    /**
     * Set the browser viewport size in pixels.
     */
    public function size(int $width, int $height): static
    {
        $this->options->setSize($width, $height);

        return $this;
    }

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
        $this->options->setFormat('png');

        return $this;
    }

    /**
     * Capture a JPEG image with the given quality.
     */
    public function jpeg(int $quality = 80): static
    {
        $this->options->setFormat('jpeg', $quality);

        return $this;
    }

    /**
     * Capture a PDF document.
     */
    public function pdf(): static
    {
        $this->options->setFormat('pdf');

        return $this;
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
     * Set page-load and individual browser-command timeouts in milliseconds.
     * Omitted values retain their current settings.
     */
    public function timeouts(?int $load = null, ?int $browser = null): static
    {
        $this->options->setTimeouts($load, $browser);

        return $this;
    }

    /**
     * Allow JavaScript execution in the captured document.
     */
    public function javascript(bool $value = true): static
    {
        $this->options->setJavascript($value);

        return $this;
    }

    /**
     * Throw when page loading times out instead of capturing partial content.
     */
    public function failOnTimeout(bool $value = true): static
    {
        $this->options->setFailOnTimeout($value);

        return $this;
    }

    /**
     * Share a browser with other workers using the same key.
     */
    public function socket(string $key): static
    {
        $this->options->setSocket($key);

        return $this;
    }

    /**
     * Use a dedicated browser for this capture.
     */
    public function withoutSocket(): static
    {
        $this->options->setSocket(null);

        return $this;
    }

    /**
     * Capture the screenshot and save it to a local path.
     */
    public function save(string $path): ScreenshotResult|PdfResult
    {
        return $this->capture()->save($path);
    }

    /**
     * Capture the screenshot and store it on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null, array $options = []): ScreenshotResult|PdfResult
    {
        return $this->capture()->store($path, $disk, $options);
    }

    /**
     * Capture the screenshot.
     */
    public function capture(): ScreenshotResult|PdfResult
    {
        $this->options->validate();

        if ($this->options->getFormat() === 'pdf') {
            if (! $this->driver instanceof PdfDriver) {
                throw new LogicException('The screenshot driver does not support PDF generation.');
            }

            $this->pdfOptions->validate();

            return $this->driver->capturePdf($this->source, $this->type, clone $this->options, clone $this->pdfOptions);
        }

        return $this->driver->capture($this->source, $this->type, clone $this->options);
    }
}
