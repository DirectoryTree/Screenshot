<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Support\ErrorHandler;
use InvalidArgumentException;

class ImageResult extends CaptureResult
{
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

        $dimensions = ErrorHandler::suppress(
            fn () => $contents === false || $contents === '' ? false : getimagesizefromstring($contents),
        );

        if (! $dimensions || ! in_array($dimensions[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw new InvalidArgumentException('The screenshot must contain a base64-encoded PNG or JPEG image.');
        }

        $this->contents = $contents;
        $this->dimensions = $dimensions;
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
}
