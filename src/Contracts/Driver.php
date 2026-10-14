<?php

namespace DirectoryTree\Screenshot\Contracts;

use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\ScreenshotResult;

interface Driver
{
    /**
     * Capture the given HTML or URL in the selected output format.
     */
    public function capture(string $source, string $type, ImageOptions $options, PdfOptions $pdf = new PdfOptions): ScreenshotResult|PdfResult;
}
