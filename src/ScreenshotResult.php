<?php

namespace DirectoryTree\Screenshot;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class ScreenshotResult
{
    /**
     * The decoded image bytes.
     *
     * @var string Binary PNG or JPEG data.
     */
    protected string $contents;

    /**
     * The image dimensions and metadata returned by getimagesizefromstring.
     *
     * @var array{0: int, 1: int, 2: int, 3: string, bits?: int, channels?: int, mime: string}
     */
    protected array $dimensions;

    /**
     * Create a result from a base64-encoded PNG or JPEG image.
     */
    public function __construct(string $base64)
    {
        $contents = base64_decode($base64, true);
        set_error_handler(static fn () => true);

        try {
            $dimensions = $contents === false || $contents === '' ? false : getimagesizefromstring($contents);
        } finally {
            restore_error_handler();
        }

        if (! $dimensions || ! in_array($dimensions[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw new InvalidArgumentException('The screenshot must contain a base64-encoded PNG or JPEG image.');
        }

        $this->contents = $contents;
        $this->dimensions = $dimensions;
    }

    /**
     * Get the image bytes.
     */
    public function contents(): string
    {
        return $this->contents;
    }

    /**
     * Get the base64-encoded image.
     */
    public function base64(): string
    {
        return base64_encode($this->contents);
    }

    /**
     * Get the image as a data URI.
     */
    public function dataUri(): string
    {
        return 'data:'.$this->mimeType().';base64,'.$this->base64();
    }

    /**
     * Get the image MIME type.
     */
    public function mimeType(): string
    {
        return $this->dimensions['mime'];
    }

    /**
     * Get the image width in pixels.
     */
    public function width(): int
    {
        return $this->dimensions[0];
    }

    /**
     * Get the image height in pixels.
     */
    public function height(): int
    {
        return $this->dimensions[1];
    }

    /**
     * Save the image to an existing local directory.
     */
    public function save(string $path): static
    {
        set_error_handler(static fn () => true);

        try {
            $written = file_put_contents($path, $this->contents);
        } finally {
            restore_error_handler();
        }

        if ($written !== strlen($this->contents)) {
            throw new RuntimeException("Unable to save the screenshot to [{$path}].");
        }

        return $this;
    }

    /**
     * Store the image on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null, array $options = []): static
    {
        if (! Storage::disk($disk)->put($path, $this->contents, $options)) {
            throw new RuntimeException("Unable to store the screenshot at [{$path}].");
        }

        return $this;
    }
}
