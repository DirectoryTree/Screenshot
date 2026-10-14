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
     * Create a recording fake that returns a small PNG by default.
     */
    public function __construct(
        protected ?ScreenshotResult $result = null,
        ?ViewFactory $views = null,
        CaptureOptions $options = new CaptureOptions,
    ) {
        parent::__construct($this, $options, $views);
    }

    /**
     * Ignore named browser shutdown while faking captures.
     */
    public function close(string $key): void {}

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
}
