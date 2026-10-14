<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowserSockets;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use LogicException;

class Screenshot
{
    /**
     * Create a new screenshot factory.
     */
    public function __construct(
        protected Driver $driver = new ChromeDriver,
        protected CaptureOptions $options = new CaptureOptions,
        protected ?ViewFactory $views = null,
    ) {}

    /**
     * Prepare a screenshot of an HTML document.
     */
    public function html(string $html): PendingScreenshot
    {
        return new PendingScreenshot($this->driver, $html, 'html', clone $this->options);
    }

    /**
     * Prepare a screenshot of an HTTP or HTTPS URL.
     */
    public function url(string $url): PendingScreenshot
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The screenshot URL must be a valid HTTP or HTTPS URL.');
        }

        return new PendingScreenshot($this->driver, $url, 'url', clone $this->options);
    }

    /**
     * Wait for active captures and stop a named shared browser.
     */
    public function closeSocket(string $key): void
    {
        $this->socketManager()->closeSocket($key);
    }

    /**
     * Wait for active captures and stop a file-backed shared browser.
     */
    public function closeSocketPath(string $path): void
    {
        $this->socketManager()->closeSocketPath($path);
    }

    /**
     * Get a driver that supports shared browser lifecycle management.
     */
    protected function socketManager(): ManagesBrowserSockets
    {
        if (! $this->driver instanceof ManagesBrowserSockets) {
            throw new LogicException('The screenshot driver does not support browser sockets.');
        }

        return $this->driver;
    }

    /**
     * Render a Laravel view and prepare its screenshot.
     */
    public function view(string $view, array $data = []): PendingScreenshot
    {
        if (! $this->views) {
            throw new LogicException('A view factory is required to capture Laravel views.');
        }

        return $this->html($this->views->make($view, $data)->render());
    }
}
