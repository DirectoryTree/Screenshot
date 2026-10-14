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
     * Create a screenshot factory with independent capture defaults.
     */
    public function __construct(
        protected Driver $driver = new ChromeDriver,
        protected BrowserOptions $browser = new BrowserOptions,
        protected ImageOptions $image = new ImageOptions,
        protected PdfOptions $pdf = new PdfOptions,
        protected ?ViewFactory $views = null,
    ) {
        $this->browser = clone $browser;
        $this->image = clone $image;
        $this->pdf = clone $pdf;
    }

    /**
     * Prepare a screenshot of an HTML document.
     */
    public function html(string $html): PendingSource
    {
        return $this->newPendingSource($html, SourceType::Html);
    }

    /**
     * Prepare a screenshot of an HTTP or HTTPS URL.
     */
    public function url(string $url): PendingSource
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The screenshot URL must be a valid HTTP or HTTPS URL.');
        }

        return $this->newPendingSource($url, SourceType::Url);
    }

    /**
     * Render a Laravel view and prepare its screenshot.
     */
    public function view(string $view, array $data = []): PendingSource
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
     * Prepare an image with independent browser and image settings.
     */
    public function newPendingImage(PendingCapture $capture, BrowserOptions $browser): PendingImage
    {
        return new PendingImage($capture, new CaptureOptions($browser, $this->image));
    }

    /**
     * Prepare a PDF with independent browser and print settings.
     */
    public function newPendingPdf(PendingCapture $capture, BrowserOptions $browser): PendingPdf
    {
        return new PendingPdf($capture, new CaptureOptions($browser, $this->pdf));
    }

    /**
     * Prepare a source with an independent snapshot of its browser settings.
     */
    protected function newPendingSource(string $source, SourceType $type): PendingSource
    {
        return new PendingSource($this, new PendingCapture($this->driver, $source, $type), $this->browser);
    }
}
