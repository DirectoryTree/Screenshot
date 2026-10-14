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
        public ?string $socketPath = null,
    ) {
        $this->validate();
    }

    /**
     * Reject unsupported options before starting a browser.
     */
    public function validate(): void
    {
        if ($this->socket !== null && trim($this->socket) === '') {
            throw new InvalidArgumentException('The socket key must not be empty.');
        }

        if ($this->socketPath !== null && (trim($this->socketPath) === '' || str_contains($this->socketPath, "\0") || str_contains($this->socketPath, '://'))) {
            throw new InvalidArgumentException('The socket path must be a local filesystem path.');
        }

        if ($this->socket !== null && $this->socketPath !== null) {
            throw new InvalidArgumentException('Specify a socket key or a socket path, not both.');
        }

        foreach ([[$this->width, $this->height], [$this->maxWidth, $this->maxHeight]] as [$width, $height]) {
            if ($width < 1 || $height < 1 || $width > 16384 || $height > 16384 || $width * $height > 40000000) {
                throw new InvalidArgumentException('Dimensions must be between 1 and 16384 pixels and no more than 40 million pixels in total.');
            }
        }

        if (! in_array($this->format, ['png', 'jpeg'], true)) {
            throw new InvalidArgumentException('The screenshot format must be png or jpeg.');
        }

        if ($this->quality < 0 || $this->quality > 100) {
            throw new InvalidArgumentException('JPEG quality must be between 0 and 100.');
        }

        if ($this->timeout < 1 || $this->loadTimeout < 1) {
            throw new InvalidArgumentException('Timeouts must be greater than zero milliseconds.');
        }
    }
}
