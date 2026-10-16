<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Drivers\Gotenberg\Request;
use HeadlessChromium\Page;
use LogicException;

class CaptureContext
{
    /**
     * Create a context for the driver's prepared page or request.
     */
    public function __construct(
        public Driver $driver,
        protected Page|Request $resource,
    ) {}

    /**
     * Get the prepared Chrome page.
     */
    public function page(): Page
    {
        return $this->resource instanceof Page
            ? $this->resource
            : throw new LogicException('The current capture does not provide a Chrome page.');
    }

    /**
     * Get the prepared Gotenberg request.
     */
    public function request(): Request
    {
        return $this->resource instanceof Request
            ? $this->resource
            : throw new LogicException('The current capture does not provide a Gotenberg request.');
    }
}
