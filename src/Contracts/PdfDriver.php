<?php

namespace DirectoryTree\Screenshot\Contracts;

use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;

interface PdfDriver
{
    /**
     * Print the given HTML or URL as a PDF.
     */
    public function capturePdf(string $source, string $type, ImageOptions $options, PdfOptions $pdf): PdfResult;
}
