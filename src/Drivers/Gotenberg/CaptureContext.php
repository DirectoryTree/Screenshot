<?php

namespace DirectoryTree\Screenshot\Drivers\Gotenberg;

use DirectoryTree\Screenshot\Contracts\CaptureContext as CaptureContextContract;
use DirectoryTree\Screenshot\Drivers\GotenbergDriver;

class CaptureContext implements CaptureContextContract
{
    /**
     * Create a context for the prepared request.
     */
    public function __construct(
        protected GotenbergDriver $driver,
        protected Request $request,
    ) {}

    /**
     * Get the driver performing the capture.
     */
    public function driver(): GotenbergDriver
    {
        return $this->driver;
    }

    /**
     * Get the prepared request.
     */
    public function request(): Request
    {
        return $this->request;
    }
}
