<?php

namespace DirectoryTree\Screenshot;

use InvalidArgumentException;

abstract class BrowserOptions
{
    /**
     * Settings explicitly assigned through the validated setters.
     *
     * @var array<string, true>
     */
    protected array $specified = [];

    /**
     * The browser viewport width in pixels.
     */
    protected int $width = 1024;

    /**
     * The browser viewport height in pixels.
     */
    protected int $height = 768;

    /**
     * The browser-operation timeout in milliseconds.
     */
    protected int $browserTimeout = 60000;

    /**
     * The page-load timeout in milliseconds.
     */
    protected int $loadTimeout = 15000;

    /**
     * Whether JavaScript execution is enabled.
     */
    protected bool $javascript = false;

    /**
     * Whether page-load timeouts should throw.
     */
    protected bool $failOnTimeout = false;

    /**
     * The shared browser key, if configured.
     */
    protected ?string $socket = null;

    /**
     * Create options using the declared defaults.
     */
    protected function __construct()
    {
        $this->validate();
    }

    /**
     * Create a fresh set of options.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Get the output format.
     */
    abstract public function getFormat(): OutputFormat;

    /**
     * Get the viewport width in pixels.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Get the viewport height in pixels.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Set the viewport dimensions after validating both values.
     */
    public function setSize(int $width, int $height): static
    {
        $this->validateDimensions($width, $height);

        $this->width = $width;
        $this->height = $height;
        $this->specified['size'] = true;

        return $this;
    }

    /**
     * Get the browser-operation timeout in milliseconds.
     */
    public function getBrowserTimeout(): int
    {
        return $this->browserTimeout;
    }

    /**
     * Set the browser-operation timeout in milliseconds.
     */
    public function setBrowserTimeout(int $milliseconds): static
    {
        return $this->setTimeouts(browser: $milliseconds);
    }

    /**
     * Get the page-load timeout in milliseconds.
     */
    public function getLoadTimeout(): int
    {
        return $this->loadTimeout;
    }

    /**
     * Set the page-load timeout in milliseconds.
     */
    public function setLoadTimeout(int $milliseconds): static
    {
        return $this->setTimeouts(load: $milliseconds);
    }

    /**
     * Set timeouts in milliseconds after validating both values.
     */
    public function setTimeouts(?int $load = null, ?int $browser = null): static
    {
        $this->validateTimeout($load ?? $this->loadTimeout);
        $this->validateTimeout($browser ?? $this->browserTimeout);

        if ($load !== null) {
            $this->loadTimeout = $load;
            $this->specified['loadTimeout'] = true;
        }

        if ($browser !== null) {
            $this->browserTimeout = $browser;
            $this->specified['browserTimeout'] = true;
        }

        return $this;
    }

    /**
     * Determine whether JavaScript execution is enabled.
     */
    public function getJavascript(): bool
    {
        return $this->javascript;
    }

    /**
     * Set whether JavaScript execution is enabled.
     */
    public function setJavascript(bool $value): static
    {
        $this->javascript = $value;
        $this->specified['javascript'] = true;

        return $this;
    }

    /**
     * Determine whether page-load timeouts should throw.
     */
    public function getFailOnTimeout(): bool
    {
        return $this->failOnTimeout;
    }

    /**
     * Set whether page-load timeouts should throw.
     */
    public function setFailOnTimeout(bool $value): static
    {
        $this->failOnTimeout = $value;
        $this->specified['failOnTimeout'] = true;

        return $this;
    }

    /**
     * Get the shared browser key, if configured.
     */
    public function getSocket(): ?string
    {
        return $this->socket;
    }

    /**
     * Set or clear the shared browser key.
     */
    public function setSocket(?string $key): static
    {
        $this->validateSocket($key);

        $this->socket = $key;
        $this->specified['socket'] = true;

        return $this;
    }

    /**
     * Treat the current values as defaults for a new capture.
     */
    public function asDefaults(): static
    {
        $options = clone $this;
        $options->specified = [];

        return $options;
    }

    /**
     * Fill unspecified settings from defaults while preserving the output format.
     * Validate the complete result before returning it.
     */
    public function withDefaults(self $defaults): static
    {
        if (! $defaults instanceof static) {
            throw new InvalidArgumentException('Defaults must use the same options type.');
        }

        $options = clone $this;
        $options->mergeDefaults($defaults);
        $options->validate();

        return $options;
    }

    /**
     * Merge shared browser defaults without replacing explicit values.
     */
    protected function mergeDefaults(self $defaults): void
    {
        if (! isset($this->specified['size'])) {
            $this->width = $defaults->width;
            $this->height = $defaults->height;
        }

        $this->browserTimeout = isset($this->specified['browserTimeout']) ? $this->browserTimeout : $defaults->browserTimeout;
        $this->loadTimeout = isset($this->specified['loadTimeout']) ? $this->loadTimeout : $defaults->loadTimeout;
        $this->javascript = isset($this->specified['javascript']) ? $this->javascript : $defaults->javascript;
        $this->failOnTimeout = isset($this->specified['failOnTimeout']) ? $this->failOnTimeout : $defaults->failOnTimeout;
        $this->socket = isset($this->specified['socket']) ? $this->socket : $defaults->socket;
    }

    /**
     * Validate shared browser settings before starting a capture.
     */
    public function validate(): void
    {
        $this->validateSocket($this->socket);
        $this->validateDimensions($this->width, $this->height);
        $this->validateTimeout($this->browserTimeout);
        $this->validateTimeout($this->loadTimeout);
    }

    /**
     * Validate a pair of dimensions before applying either value.
     */
    protected function validateDimensions(int $width, int $height): void
    {
        if ($width < 1 || $height < 1 || $width > 16384 || $height > 16384 || $width * $height > 40000000) {
            throw new InvalidArgumentException('Dimensions must be between 1 and 16384 pixels and no more than 40 million pixels in total.');
        }
    }

    /**
     * Validate a timeout in milliseconds.
     */
    protected function validateTimeout(int $milliseconds): void
    {
        if ($milliseconds < 1) {
            throw new InvalidArgumentException('Timeouts must be greater than zero milliseconds.');
        }
    }

    /**
     * Validate an optional shared browser key.
     */
    protected function validateSocket(?string $key): void
    {
        if ($key !== null && trim($key) === '') {
            throw new InvalidArgumentException('The socket key must not be empty.');
        }
    }
}
