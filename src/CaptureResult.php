<?php

namespace DirectoryTree\Screenshot;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

abstract class CaptureResult
{
    /**
     * The decoded capture bytes.
     */
    protected string $contents;

    /**
     * Get the capture's MIME type.
     */
    abstract public function mimeType(): string;

    /**
     * Get the capture bytes.
     */
    public function contents(): string
    {
        return $this->contents;
    }

    /**
     * Get the base64-encoded capture.
     */
    public function base64(): string
    {
        return base64_encode($this->contents);
    }

    /**
     * Get the capture as a data URI.
     */
    public function dataUri(): string
    {
        return 'data:'.$this->mimeType().';base64,'.$this->base64();
    }

    /**
     * Save the capture to an existing local directory.
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
            throw new RuntimeException("Unable to save the capture to [{$path}].");
        }

        return $this;
    }

    /**
     * Store the capture on a Laravel filesystem disk.
     */
    public function store(string $path, ?string $disk = null, array $options = []): static
    {
        if (! Storage::disk($disk)->put($path, $this->contents, $options)) {
            throw new RuntimeException("Unable to store the capture at [{$path}].");
        }

        return $this;
    }
}
