<?php

namespace DirectoryTree\Screenshot;

use InvalidArgumentException;

abstract class BrowserOptions
{
    /**
     * The browser viewport width in pixels.
     */
    protected int $width;

    /**
     * The browser viewport height in pixels.
     */
    protected int $height;

    /**
     * The browser-operation timeout in milliseconds.
     */
    protected int $browserTimeout;

    /**
     * The page-load timeout in milliseconds.
     */
    protected int $loadTimeout;

    /**
     * Whether JavaScript execution is enabled.
     */
    protected bool $javascript;

    /**
     * Whether page-load timeouts should throw.
     */
    protected bool $failOnTimeout;

    /**
     * The shared browser key, if configured.
     */
    protected ?string $socket;

    /**
     * Create browser options. Dimensions are pixels and timeouts are milliseconds.
     */
    public function __construct(
        int $width = 1024,
        int $height = 768,
        int $browserTimeout = 60000,
        int $loadTimeout = 15000,
        bool $javascript = false,
        bool $failOnTimeout = false,
        ?string $socket = null,
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->browserTimeout = $browserTimeout;
        $this->loadTimeout = $loadTimeout;
        $this->javascript = $javascript;
        $this->failOnTimeout = $failOnTimeout;
        $this->socket = $socket;

        $this->validate();
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
        $load ??= $this->loadTimeout;
        $browser ??= $this->browserTimeout;

        $this->validateTimeout($load);
        $this->validateTimeout($browser);

        $this->loadTimeout = $load;
        $this->browserTimeout = $browser;

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

        return $this;
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
