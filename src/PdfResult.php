<?php

namespace DirectoryTree\Screenshot;

use InvalidArgumentException;

class PdfResult extends CaptureResult
{
    /**
     * Create a result from base64-encoded PDF bytes.
     */
    public function __construct(string $base64)
    {
        $contents = base64_decode($base64, true);

        if ($contents === false || ! str_starts_with($contents, '%PDF-') || ! str_ends_with(rtrim($contents), '%%EOF')) {
            throw new InvalidArgumentException('The PDF must contain base64-encoded PDF data.');
        }

        $this->contents = $contents;
    }

    /**
     * Get the PDF MIME type.
     */
    public function mimeType(): string
    {
        return 'application/pdf';
    }
}
