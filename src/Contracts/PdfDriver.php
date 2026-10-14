<?php

namespace DirectoryTree\Screenshot\Contracts;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;

interface PdfDriver
{
    /**
     * Print the given HTML or URL as a PDF.
     */
    public function capturePdf(string $source, string $type, CaptureOptions $options, PdfOptions $pdf): PdfResult;
}
