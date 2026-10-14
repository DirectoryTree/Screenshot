<?php

namespace DirectoryTree\Screenshot\Contracts;

interface ManagesBrowserSockets
{
    /**
     * Wait for active captures and close a named shared browser.
     */
    public function closeSocket(string $key): void;

    /**
     * Wait for active captures and close a file-backed shared browser.
     */
    public function closeSocketPath(string $path): void;
}
