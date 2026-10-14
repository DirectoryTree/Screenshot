<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Enums\PaperSize;

class PendingPdf
{
    /**
     * Create a PDF builder with independent print settings.
     *
     * @param  CaptureOptions<PdfOptions>  $options
     */
    public function __construct(
        protected PendingCapture $capture,
        protected CaptureOptions $options,
    ) {
        $this->options = clone $options;
    }

    /**
     * Set the viewport dimensions after validating both values.
     */
    public function size(int $width, int $height): static
    {
        $this->options->browser->setSize($width, $height);

        return $this;
    }

    /**
     * Set page-load and individual browser-command timeouts in milliseconds.
     */
    public function timeouts(?int $load = null, ?int $browser = null): static
    {
        $this->options->browser->setTimeouts($load, $browser);

        return $this;
    }

    /**
     * Allow JavaScript execution in the captured document.
     */
    public function javascript(bool $value = true): static
    {
        $this->options->browser->setJavascript($value);

        return $this;
    }

    /**
     * Throw when page loading times out instead of capturing partial content.
     */
    public function failOnTimeout(bool $value = true): static
    {
        $this->options->browser->setFailOnTimeout($value);

        return $this;
    }

    /**
     * Share a browser with other workers using the same key.
     */
    public function socket(string $key): static
    {
        $this->options->browser->setSocket($key);

        return $this;
    }

    /**
     * Use a dedicated browser for this capture.
     */
    public function withoutSocket(): static
    {
        $this->options->browser->setSocket(null);

        return $this;
    }

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

    /**
     * Capture the document and save it to a local path.
     */
    public function save(string $path): PdfResult
    {
        return $this->capture()->save($path);
    }

    /**
     * Capture the document and store it on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null, array $options = []): PdfResult
    {
        return $this->capture()->store($path, $disk, $options);
    }

    /**
     * Capture the document using its current settings.
     */
    public function capture(): PdfResult
    {
        return $this->capture->capture($this->options);
    }
}
