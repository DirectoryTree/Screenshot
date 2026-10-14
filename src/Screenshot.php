<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowsers;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\SourceType;
use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use LogicException;

class Screenshot
{
    /**
     * The shared browser defaults.
     */
    protected BrowserOptions $browser;

    /**
     * The image output defaults.
     */
    protected ImageOptions $image;

    /**
     * The PDF output defaults.
     */
    protected PdfOptions $pdf;

    /**
     * Create a screenshot factory with independent capture defaults.
     */
    public function __construct(
        protected Driver $driver = new ChromeDriver,
        ?BrowserOptions $browser = null,
        ?ImageOptions $image = null,
        ?PdfOptions $pdf = null,
        protected ?ViewFactory $views = null,
    ) {
        $this->browser = $browser ? clone $browser : BrowserOptions::make();
        $this->image = $image ? clone $image : ImageOptions::make();
        $this->pdf = $pdf ? clone $pdf : PdfOptions::make();
    }

    /**
     * Prepare a screenshot of an HTML document.
     */
    public function html(string $html): PendingImage
    {
        return $this->newPendingImage($html, SourceType::Html);
    }

    /**
     * Prepare a screenshot of an HTTP or HTTPS URL.
     */
    public function url(string $url): PendingImage
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The screenshot URL must be a valid HTTP or HTTPS URL.');
        }

        return $this->newPendingImage($url, SourceType::Url);
    }

    /**
     * Render a Laravel view and prepare its screenshot.
     */
    public function view(string $view, array $data = []): PendingImage
    {
        if (! $this->views) {
            throw new LogicException('A view factory is required to capture Laravel views.');
        }

        return $this->html($this->views->make($view, $data)->render());
    }

    /**
     * Wait for active captures and stop a named shared browser.
     */
    public function close(string $key): void
    {
        $this->sockets()->close($key);
    }

    /**
     * Get a driver that supports shared browser lifecycle management.
     */
    protected function sockets(): ManagesBrowsers
    {
        if (! $this->driver instanceof ManagesBrowsers) {
            throw new LogicException('The screenshot driver does not support browser sockets.');
        }

        return $this->driver;
    }

    /**
     * Prepare a capture with an independent snapshot of its defaults.
     */
    protected function newPendingImage(string $source, SourceType $type): PendingImage
    {
        return new PendingImage(
            $this->driver, $source, $type,
            new CaptureOptions($this->browser, $this->image), $this->pdf,
        );
    }
}
