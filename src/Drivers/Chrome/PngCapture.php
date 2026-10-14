<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\Enums\OutputFormat;

class PngCapture extends ImageCapture
{
    /**
     * Get the PNG screenshot parameters.
     */
    protected function parameters(): array
    {
        return ['format' => OutputFormat::Png->value];
    }
}
