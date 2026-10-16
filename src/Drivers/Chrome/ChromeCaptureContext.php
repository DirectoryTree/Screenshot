<?php

namespace DirectoryTree\Screenshot\Drivers\Chrome;

use DirectoryTree\Screenshot\Contracts\CaptureContext;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use HeadlessChromium\Page;

class ChromeCaptureContext implements CaptureContext
{
    /**
     * Create a context for the prepared page.
     */
    public function __construct(
        protected ChromeDriver $driver,
        protected Page $page,
    ) {}

    /**
     * Get the driver performing the capture.
     */
    public function driver(): ChromeDriver
    {
        return $this->driver;
    }

    /**
     * Get the prepared page.
     */
    public function page(): Page
    {
        return $this->page;
    }
}
