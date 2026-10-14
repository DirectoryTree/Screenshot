<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;
use HeadlessChromium\Page;

interface CapturesPage
{
    /**
     * Capture the prepared page in the selected output format.
     */
    public function capture(Page $page): ImageResult|PdfResult;
}
