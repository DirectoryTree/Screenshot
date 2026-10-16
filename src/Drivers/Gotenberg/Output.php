<?php

namespace DirectoryTree\Screenshot\Drivers\Gotenberg;

use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;

interface Output
{
    /**
     * Get the Chromium route for the selected output format.
     */
    public function route(): string;

    /**
     * Get the form fields for the selected output format.
     */
    public function fields(): array;

    /**
     * Create a capture result from the response bytes.
     */
    public function result(string $contents): ImageResult|PdfResult;
}
