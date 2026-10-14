<?php

namespace DirectoryTree\Screenshot\Contracts;

interface ManagesBrowsers
{
    /**
     * Wait for active captures and close a named shared browser.
     */
    public function close(string $key): void;
}
