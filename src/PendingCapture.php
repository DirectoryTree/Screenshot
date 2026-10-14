<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Enums\SourceType;

class PendingCapture
{
    /**
     * Prepare a source for execution through the given driver.
     */
    public function __construct(
        protected Driver $driver,
        protected string $source,
        protected SourceType $type,
    ) {}

    /**
     * Capture an independent snapshot of the given settings.
     */
    public function capture(CaptureOptions $options): ImageResult|PdfResult
    {
        $options = clone $options;
        $options->validate();

        return $this->driver->capture($this->source, $this->type, $options);
    }
}
