<?php

namespace DirectoryTree\Screenshot;

use Closure;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowsers;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use LogicException;

class Screenshot
{
    /**
     * The default configuration callbacks keyed by output format.
     *
     * @var array<string, Closure>
     */
    protected array $configureUsing = [];

    /**
     * The default image and browser options.
     */
    protected ImageOptions $options;

    /**
     * Create a new screenshot factory.
     */
    public function __construct(
        protected Driver $driver = new ChromeDriver,
        ?ImageOptions $options = null,
        protected ?ViewFactory $views = null,
    ) {
        $this->options = $options ?? ImageOptions::make();
    }

    /**
     * Set or clear the default configuration callback for an output format.
     *
     * @param  (callable(PendingImage|PendingPdf): mixed)|null  $callback
     */
    public function configure(OutputFormat $format, ?callable $callback): static
    {
        if ($callback === null) {
            unset($this->configureUsing[$format->value]);
        } else {
            $this->configureUsing[$format->value] = Closure::fromCallable($callback);
        }

        return $this;
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
     * Prepare a capture with an independent snapshot of its format callbacks.
     */
    protected function newPendingImage(string $source, SourceType $type): PendingImage
    {
        return new PendingImage($this->driver, $source, $type, $this->options, $this->configureUsing);
    }
}
