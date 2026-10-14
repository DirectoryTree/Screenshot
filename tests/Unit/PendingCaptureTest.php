<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\PendingCapture;
use DirectoryTree\Screenshot\PendingImage;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('one executor supports independent image and PDF builders for the same source', function () {
    $fake = new ScreenshotFake;
    $capture = new PendingCapture($fake, 'https://example.com/invoice', SourceType::Url);
    $image = new PendingImage($capture, new CaptureOptions(new BrowserOptions, new ImageOptions));
    $pdf = new PendingPdf($capture, new CaptureOptions(new BrowserOptions, new PdfOptions));

    $image->size(800, 600)->jpeg(95);
    $pdf->size(400, 300)->landscape();

    $fake->assertNothingCaptured();

    expect($image->capture())->toBeInstanceOf(ImageResult::class);
    expect($pdf->capture())->toBeInstanceOf(PdfResult::class);

    $image->capture();

    $fake->assertCaptured(
        fn ($source, $type, $options) => $source === 'https://example.com/invoice'
            && $type === SourceType::Url
            && $options->browser()->getWidth() === 800
            && $options->format() === OutputFormat::Jpeg
            && $options->output()->getQuality() === 95,
    );
    $fake->assertCaptured(
        fn ($source, $type, $options) => $options->format() === OutputFormat::Pdf
            && $source === 'https://example.com/invoice'
            && $type === SourceType::Url
            && $options->browser()->getWidth() === 400
            && $options->output()->getLandscape(),
    );
    $fake->assertCapturedCount(3);
});

test('the executor snapshots options before passing them to a driver', function () {
    $options = new CaptureOptions((new BrowserOptions)->setSize(800, 600), new ImageOptions);
    $result = new ImageResult(imageFixture());
    $driver = Mockery::mock(Driver::class);

    $driver->shouldReceive('capture')
        ->once()
        ->withArgs(function ($source, $type, $snapshot) use ($options) {
            expect($source)->toBe('invoice');
            expect($type)->toBe(SourceType::Html);
            expect($snapshot)->not->toBe($options);
            expect($snapshot->browser())->not->toBe($options->browser());
            expect($snapshot->output())->not->toBe($options->output());

            $snapshot->browser()->setSize(400, 300);
            $snapshot->output()->setFormat(OutputFormat::Jpeg, 50);

            return true;
        })
        ->andReturn($result);

    $capture = new PendingCapture($driver, 'invoice', SourceType::Html);

    expect($capture->capture($options))->toBe($result);
    expect($options->browser()->getWidth())->toBe(800);
    expect($options->format())->toBe(OutputFormat::Png);
    expect($options->output()->getQuality())->toBe(80);
});

test('the executor validates settings before invoking the driver', function () {
    $options = new class(new BrowserOptions, new ImageOptions) extends CaptureOptions
    {
        public function validate(): void
        {
            throw new InvalidArgumentException('Invalid capture settings.');
        }
    };
    $driver = Mockery::mock(Driver::class);

    $driver->shouldNotReceive('capture');

    $capture = new PendingCapture($driver, 'invoice', SourceType::Html);

    expect(fn () => $capture->capture($options))
        ->toThrow(InvalidArgumentException::class, 'Invalid capture settings.');
});
