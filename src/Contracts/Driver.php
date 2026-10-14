<?php

namespace DirectoryTree\Screenshot\Contracts;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;

interface Driver
{
    /**
     * Capture the given HTML or URL in the selected output format.
     */
    public function capture(string $source, SourceType $type, CaptureOptions $options): ImageResult|PdfResult;
}
