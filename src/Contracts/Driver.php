<?php

namespace DirectoryTree\Screenshot\Contracts;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\ScreenshotResult;

interface Driver
{
    /**
     * Capture the given HTML or URL.
     */
    public function capture(string $source, string $type, CaptureOptions $options): ScreenshotResult;
}
