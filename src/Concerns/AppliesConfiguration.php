<?php

namespace DirectoryTree\Screenshot\Concerns;

use Closure;
use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\ImageOptions;

trait AppliesConfiguration
{
    /**
     * The capture options before caller-specific changes.
     */
    protected BrowserOptions $defaults;

    /**
     * Validated caller-specific changes, replayed after format defaults.
     *
     * @var list<Closure(BrowserOptions): mixed>
     */
    protected array $overrides = [];

    /**
     * Validate and remember an explicit browser option change.
     */
    protected function configureOptions(Closure $callback): static
    {
        $callback($this->options);
        $this->overrides[] = $callback;

        return $this;
    }

    /**
     * Apply only the selected format's defaults, then explicit options.
     */
    protected function configured(): static
    {
        $capture = clone $this;
        $format = $this->options->getFormat();
        $capture->options = clone $this->defaults;

        if ($capture->options instanceof ImageOptions) {
            $capture->options->setFormat($format);
        }

        $capture->configureUsing = [];
        $capture->overrides = [];

        if ($callback = $this->configureUsing[$format->value] ?? null) {
            $callback($capture);
        }

        foreach ($this->overrides as $override) {
            $override($capture->options);
        }

        $capture->options = $this->configuredOutputOptions($capture->options);

        if ($capture->options instanceof ImageOptions) {
            $capture->options->setFormat($format);
        }

        return $capture;
    }
}
