<?php

namespace DirectoryTree\Screenshot;

use InvalidArgumentException;

class CaptureOptions
{
    /**
     * Create a new set of capture options. Timeouts are in milliseconds.
     */
    public function __construct(
        protected int $width = 1024,
        protected int $height = 768,
        protected int $maxWidth = 2048,
        protected int $maxHeight = 10000,
        protected string $format = 'png',
        protected int $quality = 80,
        protected int $timeout = 60000,
        protected int $loadTimeout = 15000,
        protected bool $fullPage = true,
        protected bool $javascript = false,
        protected bool $failOnTimeout = false,
        protected ?string $socket = null,
    ) {
        $this->validate();
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
     * Get the image format.
     */
    public function getFormat(): string
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
     * Set the image format and optional JPEG quality atomically.
     */
    public function setFormat(string $format, ?int $quality = null): static
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
     * Get the browser-operation timeout in milliseconds.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set the browser-operation timeout in milliseconds.
     */
    public function setTimeout(int $milliseconds): static
    {
        $this->validateTimeout($milliseconds);

        $this->timeout = $milliseconds;

        return $this;
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
        $this->validateTimeout($milliseconds);

        $this->loadTimeout = $milliseconds;

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
     * Reject unsupported options before starting a browser.
     */
    public function validate(): void
    {
        $this->validateSocket($this->socket);
        $this->validateDimensions($this->width, $this->height);
        $this->validateDimensions($this->maxWidth, $this->maxHeight);
        $this->validateFormat($this->format);
        $this->validateQuality($this->quality);
        $this->validateTimeout($this->timeout);
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
     * Validate the image format.
     */
    protected function validateFormat(string $format): void
    {
        if (! in_array($format, ['png', 'jpeg'], true)) {
            throw new InvalidArgumentException('The screenshot format must be png or jpeg.');
        }
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
