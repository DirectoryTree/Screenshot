<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Enums\SourceType;

/**
 * @template TOptions of ImageOptions|PdfOptions
 * @template TResult of ImageResult|PdfResult
 */
abstract class PendingCapture
{
    /**
     * Create a capture with an independent snapshot of its settings.
     *
     * @param  CaptureOptions<TOptions>  $options
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected SourceType $type,
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
     * Omitted values retain their current settings.
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
     * Capture the document and save it to a local path.
     *
     * @return TResult
     */
    public function save(string $path): ImageResult|PdfResult
    {
        return $this->capture()->save($path);
    }

    /**
     * Capture the document and store it on a Laravel filesystem disk.
     *
     * @return TResult
     */
    public function store(string $path, ?string $disk = null, array $options = []): ImageResult|PdfResult
    {
        return $this->capture()->store($path, $disk, $options);
    }

    /**
     * Capture an independent snapshot of the current settings.
     *
     * @return TResult
     */
    public function capture(): ImageResult|PdfResult
    {
        $options = clone $this->options;
        $options->validate();

        return $this->driver->capture($this->source, $this->type, $options);
    }
}
