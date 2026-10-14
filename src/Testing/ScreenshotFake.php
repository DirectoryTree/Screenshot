<?php

namespace DirectoryTree\Screenshot\Testing;

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
     * @var list<array{string, SourceType, ImageOptions|PdfOptions}>
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
        ?ImageOptions $options = null,
    ) {
        parent::__construct($this, $options ? clone $options : null, $views);
    }

    /**
     * Create a fresh fake using an existing factory's configuration.
     */
    public static function from(Screenshot $factory, ImageResult|PdfResult|null $result = null): static
    {
        $fake = new static(
            result: $result,
            views: $factory->views,
            options: $factory->options,
        );

        $fake->configureUsing = $factory->configureUsing;

        return $fake;
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
    public function capture(string $source, SourceType $type, ImageOptions|PdfOptions $options): ImageResult|PdfResult
    {
        $this->captures[] = [$source, $type, clone $options];

        if ($options->getFormat() === OutputFormat::Pdf) {
            return $this->result instanceof PdfResult
                ? $this->result
                : new PdfResult(base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf')));
        }

        return $this->result instanceof ImageResult
            ? $this->result
            : new ImageResult('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
    }

    /**
     * Assert that a PDF matching the callback was captured.
     */
    public function assertPdfCaptured(?callable $callback = null): void
    {
        foreach ($this->capturesOfType(PdfOptions::class) as [$source, $type, $options]) {
            if (! $callback || $callback($source, $type, clone $options)) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail('The expected PDF was not captured.');
    }

    /**
     * Assert the number of captured PDFs.
     */
    public function assertPdfCapturedCount(int $count): void
    {
        Assert::assertCount($count, $this->capturesOfType(PdfOptions::class));
    }

    /**
     * Assert that no PDFs were captured.
     */
    public function assertNoPdfsCaptured(): void
    {
        $this->assertPdfCapturedCount(0);
    }

    /**
     * Assert that a screenshot matching the callback was captured.
     */
    public function assertCaptured(?callable $callback = null): void
    {
        foreach ($this->capturesOfType(ImageOptions::class) as [$source, $type, $options]) {
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
        Assert::assertCount($count, $this->capturesOfType(ImageOptions::class));
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

    /**
     * Filter the shared capture history by its options type.
     *
     * @param  class-string<ImageOptions|PdfOptions>  $type
     * @return list<array{string, SourceType, ImageOptions|PdfOptions}>
     */
    protected function capturesOfType(string $type): array
    {
        return array_values(array_filter(
            $this->captures,
            static fn (array $capture) => $capture[2] instanceof $type,
        ));
    }
}
