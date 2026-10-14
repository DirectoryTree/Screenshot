<?php

namespace DirectoryTree\Screenshot\Contracts;

use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\SourceType;

interface Driver
{
    /**
     * Capture the given HTML or URL in the selected output format.
     */
    public function capture(string $source, SourceType $type, ImageOptions|PdfOptions $options): ImageResult|PdfResult;
}
