<?php

namespace DirectoryTree\Screenshot\Testing;

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\Screenshot;
use Illuminate\Contracts\View\Factory as ViewFactory;
use PHPUnit\Framework\Assert;

class ScreenshotFake extends Screenshot implements Driver
{
    /**
     * The recorded image and PDF captures, in capture order.
     *
     * @var list<array{string, SourceType, CaptureOptions}>
     */
    protected array $captures = [];

    /**
     * The recorded browser shutdown requests.
     *
     * @var list<string>
     */
    protected array $closed = [];

    /**
     * Create a recording fake with an optional image or PDF result.
     */
    public function __construct(
        protected ImageResult|PdfResult|null $result = null,
        ?ViewFactory $views = null,
        BrowserOptions $browser = new BrowserOptions,
        ImageOptions $image = new ImageOptions,
        PdfOptions $pdf = new PdfOptions,
    ) {
        parent::__construct($this, $browser, $image, $pdf, $views);
    }

    /**
     * Create a fresh fake using an existing factory's configuration.
     */
    public static function from(Screenshot $factory, ImageResult|PdfResult|null $result = null): static
    {
        return new static(
            result: $result,
            views: $factory->views,
            browser: $factory->browser,
            image: $factory->image,
            pdf: $factory->pdf,
        );
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
    public function capture(string $source, SourceType $type, CaptureOptions $options): ImageResult|PdfResult
    {
        $this->captures[] = [$source, $type, clone $options];

        if ($options->format() === OutputFormat::Pdf) {
            return $this->result instanceof PdfResult
                ? $this->result
                : new PdfResult(base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf')));
        }

        return $this->result instanceof ImageResult
            ? $this->result
            : new ImageResult('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
    }

    /**
     * Assert that an image or PDF matching the callback was captured.
     */
    public function assertCaptured(?callable $callback = null): void
    {
        foreach ($this->captures as [$source, $type, $options]) {
            if (! $callback || $callback($source, $type, clone $options)) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail('The expected capture was not recorded.');
    }

    /**
     * Assert the total number of image and PDF captures.
     */
    public function assertCapturedCount(int $count): void
    {
        Assert::assertCount($count, $this->captures);
    }

    /**
     * Assert that no images or PDFs were captured.
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
