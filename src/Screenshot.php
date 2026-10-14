<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
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
