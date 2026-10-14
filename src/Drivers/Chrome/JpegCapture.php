<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\OutputFormat;

class JpegCapture extends ImageCapture
{
    /**
     * Get the JPEG screenshot parameters, including quality.
     */
    protected function parameters(): array
    {
        return [
            'format' => OutputFormat::Jpeg->value,
            'quality' => $this->options->getQuality(),
        ];
    }
}
