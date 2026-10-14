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
    $image = new PendingImage($capture, new CaptureOptions(BrowserOptions::make(), ImageOptions::make()));
    $pdf = new PendingPdf($capture, new CaptureOptions(BrowserOptions::make(), PdfOptions::make()));

    $image->size(800, 600)->jpeg(95);
    $pdf->size(400, 300)->landscape();
    $fake->assertNothingCaptured();
    $fake->assertNoPdfsCaptured();

    expect($image->capture())->toBeInstanceOf(ImageResult::class)
        ->and($pdf->capture())->toBeInstanceOf(PdfResult::class);
    $image->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'https://example.com/invoice'
        && $type === SourceType::Url && $options->browser->getWidth() === 800
        && $options->getFormat() === OutputFormat::Jpeg && $options->output->getQuality() === 95);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $source === 'https://example.com/invoice'
        && $type === SourceType::Url && $options->browser->getWidth() === 400
        && $options->output->getLandscape());
    $fake->assertCapturedCount(2);
    $fake->assertPdfCapturedCount(1);
});

test('the executor snapshots options before passing them to a driver', function () {
    $options = new CaptureOptions(BrowserOptions::make()->setSize(800, 600), ImageOptions::make());
    $result = new ImageResult(imageFixture());
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capture')->once()->withArgs(function ($source, $type, $snapshot) use ($options) {
        expect($source)->toBe('invoice')->and($type)->toBe(SourceType::Html)
            ->and($snapshot)->not->toBe($options)
            ->and($snapshot->browser)->not->toBe($options->browser)
            ->and($snapshot->output)->not->toBe($options->output);

        $snapshot->browser->setSize(400, 300);
        $snapshot->output->setFormat(OutputFormat::Jpeg, 50);

        return true;
    })->andReturn($result);
    $capture = new PendingCapture($driver, 'invoice', SourceType::Html);

    expect($capture->capture($options))->toBe($result)
        ->and($options->browser->getWidth())->toBe(800)
        ->and($options->getFormat())->toBe(OutputFormat::Png)
        ->and($options->output->getQuality())->toBe(80);
});

test('the executor validates settings before invoking the driver', function () {
    $options = new class(BrowserOptions::make(), ImageOptions::make()) extends CaptureOptions
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
