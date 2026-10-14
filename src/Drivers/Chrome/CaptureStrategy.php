<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;
use HeadlessChromium\Page;

interface CaptureStrategy
{
    /**
     * Capture the prepared page in the selected output format.
     */
    public function capture(Page $page): ImageResult|PdfResult;
}
