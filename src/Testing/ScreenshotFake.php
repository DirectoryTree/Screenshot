<?php

namespace DirectoryTree\Screenshot\Testing;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\ScreenshotResult;
use Illuminate\Contracts\View\Factory as ViewFactory;
use PHPUnit\Framework\Assert;

class ScreenshotFake extends Screenshot implements Driver
{
    /**
     * The recorded captures.
     */
    protected array $captures = [];

    /**
     * The recorded browser shutdown requests.
     */
    protected array $closed = [];

    /**
     * Create a recording fake that returns a small PNG by default.
     */
    public function __construct(
        protected ?ScreenshotResult $result = null,
        ?ViewFactory $views = null,
        CaptureOptions $options = new CaptureOptions,
    ) {
        parent::__construct($this, clone $options, $views);
    }

    /**
     * Create a fresh fake using an existing factory's configuration.
     */
    public static function from(Screenshot $factory, ?ScreenshotResult $result = null): static
    {
        return (new static(
            result: $result,
            views: $factory->views,
            options: $factory->options,
        ))->configure($factory->configureUsing);
    }

    /**
     * Record a browser shutdown request without connecting to Chrome.
     */
    public function close(string $key): void
    {
        $this->closed[] = $key;
    }

    /**
     * Record a capture without starting Chrome.
     */
    public function capture(string $source, string $type, CaptureOptions $options): ScreenshotResult
    {
        $this->captures[] = [$source, $type, clone $options];

        return $this->result ?? new ScreenshotResult(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='
        );
    }

    /**
     * Assert that a screenshot matching the callback was captured.
     */
    public function assertCaptured(?callable $callback = null): void
    {
        foreach ($this->captures as [$source, $type, $options]) {
            if (! $callback || $callback($source, $type, clone $options)) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail('The expected screenshot was not captured.');
    }

    /**
     * Assert the number of captured screenshots.
     */
    public function assertCapturedCount(int $count): void
    {
        Assert::assertCount($count, $this->captures);
    }

    /**
     * Assert that no screenshots were captured.
     */
    public function assertNothingCaptured(): void
    {
        $this->assertCapturedCount(0);
    }

    /**
     * Assert that a shutdown was requested for the given browser key.
     */
    public function assertClosed(string $key): void
    {
        Assert::assertTrue(
            in_array($key, $this->closed, true),
            "The expected browser [{$key}] was not closed.",
        );
    }

    /**
     * Assert that no shutdown was requested for the given browser key.
     */
    public function assertNotClosed(string $key): void
    {
        Assert::assertFalse(
            in_array($key, $this->closed, true),
            "The unexpected browser [{$key}] was closed.",
        );
    }

    /**
     * Assert that no browser shutdowns were requested.
     */
    public function assertNothingClosed(): void
    {
        Assert::assertCount(0, $this->closed, 'Unexpected browser shutdowns were requested.');
    }
}
