<?php

namespace DirectoryTree\Screenshot;

use InvalidArgumentException;

class CaptureOptions
{
    /**
     * Create a new set of capture options. Timeouts are in milliseconds.
     */
    public function __construct(
        public int $width = 1024,
        public int $height = 768,
        public int $maxWidth = 2048,
        public int $maxHeight = 10000,
        public string $format = 'png',
        public int $quality = 80,
        public int $timeout = 60000,
        public int $loadTimeout = 15000,
        public bool $fullPage = true,
        public bool $javascript = false,
        public bool $failOnTimeout = false,
        public ?string $socket = null,
    ) {
        $this->validate();
    }

    /**
     * Reject unsupported options before starting a browser.
     */
    public function validate(): void
    {
        static::validateSocket($this->socket);
        static::validateDimensions($this->width, $this->height);
        static::validateDimensions($this->maxWidth, $this->maxHeight);

        if (! in_array($this->format, ['png', 'jpeg'], true)) {
            throw new InvalidArgumentException('The screenshot format must be png or jpeg.');
        }

        static::validateQuality($this->quality);
        static::validateTimeout($this->timeout);
        static::validateTimeout($this->loadTimeout);
    }

    /**
     * Validate a pair of dimensions before applying either value.
     */
    public static function validateDimensions(int $width, int $height): void
    {
        if ($width < 1 || $height < 1 || $width > 16384 || $height > 16384 || $width * $height > 40000000) {
            throw new InvalidArgumentException('Dimensions must be between 1 and 16384 pixels and no more than 40 million pixels in total.');
        }
    }

    /**
     * Validate the JPEG quality before selecting the format.
     */
    public static function validateQuality(int $quality): void
    {
        if ($quality < 0 || $quality > 100) {
            throw new InvalidArgumentException('JPEG quality must be between 0 and 100.');
        }
    }

    /**
     * Validate a timeout in milliseconds.
     */
    public static function validateTimeout(int $milliseconds): void
    {
        if ($milliseconds < 1) {
            throw new InvalidArgumentException('Timeouts must be greater than zero milliseconds.');
        }
    }

    /**
     * Validate an optional shared browser key.
     */
    public static function validateSocket(?string $key): void
    {
        if ($key !== null && trim($key) === '') {
            throw new InvalidArgumentException('The socket key must not be empty.');
        }
    }
}
