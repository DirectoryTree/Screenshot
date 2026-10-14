<?php

namespace DirectoryTree\Screenshot\Concerns;

use Closure;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\PendingPdf;

trait AppliesConfiguration
{
    /**
     * The browser and image options before caller-specific changes.
     */
    protected ImageOptions $defaults;

    /**
     * Validated caller-specific changes, replayed after format defaults.
     *
     * @var list<Closure(ImageOptions): mixed>
     */
    protected array $overrides = [];

    /**
     * Validate and remember an explicit browser or image option change.
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
        $capture->options = (clone $this->defaults)->setFormat($format);
        $capture->configureUsing = [];
        $capture->overrides = [];

        if ($capture instanceof PendingPdf) {
            $capture->pdfOptions = clone $this->pdfDefaults;
        }

        if ($callback = $this->configureUsing[$format->value] ?? null) {
            $callback($capture);
        }

        foreach ($this->overrides as $override) {
            $override($capture->options);
        }

        if ($capture instanceof PendingPdf) {
            $capture->pdfOptions = $this->configuredPrintOptions($capture->pdfOptions);
        }

        $capture->options->setFormat($format);

        return $capture;
    }
}
