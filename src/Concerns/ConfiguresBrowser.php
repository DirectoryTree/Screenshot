<?php

namespace DirectoryTree\Screenshot\Concerns;

use DirectoryTree\Screenshot\BrowserOptions;

trait ConfiguresBrowser
{
    /**
     * Set the viewport dimensions after validating both values.
     */
    public function size(int $width, int $height): static
    {
        return $this->configureOptions(static fn (BrowserOptions $options) => $options->setSize($width, $height));
    }

    /**
     * Set page-load and individual browser-command timeouts in milliseconds.
     * Omitted values retain their current settings.
     */
    public function timeouts(?int $load = null, ?int $browser = null): static
    {
        return $this->configureOptions(static fn (BrowserOptions $options) => $options->setTimeouts($load, $browser));
    }

    /**
     * Allow JavaScript execution in the captured document.
     */
    public function javascript(bool $value = true): static
    {
        return $this->configureOptions(static fn (BrowserOptions $options) => $options->setJavascript($value));
    }

    /**
     * Throw when page loading times out instead of capturing partial content.
     */
    public function failOnTimeout(bool $value = true): static
    {
        return $this->configureOptions(static fn (BrowserOptions $options) => $options->setFailOnTimeout($value));
    }

    /**
     * Share a browser with other workers using the same key.
     */
    public function socket(string $key): static
    {
        return $this->configureOptions(static fn (BrowserOptions $options) => $options->setSocket($key));
    }

    /**
     * Use a dedicated browser for this capture.
     */
    public function withoutSocket(): static
    {
        return $this->configureOptions(static fn (BrowserOptions $options) => $options->setSocket(null));
    }
}
