<?php

namespace DirectoryTree\Screenshot\Contracts;

interface CaptureContext
{
    /**
     * Get the driver performing the capture.
     */
    public function driver(): Driver;
}
