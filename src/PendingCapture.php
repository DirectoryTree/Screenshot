<?php

namespace DirectoryTree\Screenshot;

use Closure;
use DirectoryTree\Screenshot\Contracts\Driver;

/**
 * @template TOptions of ImageOptions|PdfOptions
 * @template TResult of ImageResult|PdfResult
 */
abstract class PendingCapture
{
    /**
     * The options before per-capture settings are applied.
     *
     * @var TOptions
     */
    protected BrowserOptions $defaults;

    /**
     * Create an independent capture with its format configuration callbacks.
     *
     * @param  TOptions  $options
     * @param  array<string, Closure>  $configureUsing
     * @param  TOptions|null  $defaults
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected SourceType $type,
        protected BrowserOptions $options,
        protected array $configureUsing = [],
        ?BrowserOptions $defaults = null,
    ) {
        $this->defaults = ($defaults ?? $options)->asDefaults();
        $this->options = $defaults ? clone $options : $options->asDefaults();
    }

    /**
     * Set the viewport dimensions after validating both values.
     */
    public function size(int $width, int $height): static
    {
        $this->options->setSize($width, $height);

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
     * Capture using format defaults followed by explicit settings.
     *
     * @return TResult
     */
    public function capture(): ImageResult|PdfResult
    {
        $capture = clone $this;
        $capture->options = $this->options->asDefaults()->withDefaults($this->defaults);
        $capture->configureUsing = [];
        $format = $this->options->getFormat();

        if ($callback = $this->configureUsing[$format->value] ?? null) {
            $callback($capture);
        }

        $options = $this->options->withDefaults($capture->options);

        return $this->driver->capture($this->source, $this->type, $options->asDefaults());
    }
}
