<?php

namespace DirectoryTree\Screenshot;

use Closure;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowsers;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use LogicException;

class Screenshot
{
    /**
     * The callback used to configure each new pending screenshot.
     *
     * @var (Closure(PendingScreenshot): mixed)|null
     */
    protected ?Closure $configureUsing = null;

    /**
     * Create a new screenshot factory.
     */
    public function __construct(
        protected Driver $driver = new ChromeDriver,
        protected CaptureOptions $options = new CaptureOptions,
        protected ?ViewFactory $views = null,
    ) {}

    /**
     * Set or clear the default configuration callback for future screenshots.
     *
     * @param  (callable(PendingScreenshot): mixed)|null  $callback
     */
    public function configure(?callable $callback): static
    {
        $this->configureUsing = $callback === null ? null : Closure::fromCallable($callback);

        return $this;
    }

    /**
     * Prepare a screenshot of an HTML document.
     */
    public function html(string $html): PendingScreenshot
    {
        return $this->newPendingScreenshot($html, 'html');
    }

    /**
     * Prepare a screenshot of an HTTP or HTTPS URL.
     */
    public function url(string $url): PendingScreenshot
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The screenshot URL must be a valid HTTP or HTTPS URL.');
        }

        return $this->newPendingScreenshot($url, 'url');
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

    /**
     * Apply defaults to a fresh screenshot before caller-specific options.
     */
    protected function newPendingScreenshot(string $source, string $type): PendingScreenshot
    {
        $screenshot = new PendingScreenshot($this->driver, $source, $type, $this->options);

        if ($this->configureUsing) {
            ($this->configureUsing)($screenshot);
        }

        return $screenshot;
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
}
