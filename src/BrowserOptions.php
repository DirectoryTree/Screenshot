<?php

namespace DirectoryTree\Screenshot;

use InvalidArgumentException;

class BrowserOptions
{
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
        $this->validateTimeout($load ?? $this->loadTimeout);
        $this->validateTimeout($browser ?? $this->browserTimeout);

        if ($load !== null) {
            $this->loadTimeout = $load;
        }

        if ($browser !== null) {
            $this->browserTimeout = $browser;
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
        $this->validateTimeout($this->loadTimeout);
        $this->validateTimeout($this->browserTimeout);
        $this->validateDimensions($this->width, $this->height);
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
