<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\PendingImage;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use PHPUnit\Framework\AssertionFailedError;

function pdfFixture(): string
{
    return base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf'));
}

test('PDF conversion snapshots browser defaults without capturing or changing the source', function () {
    $fake = new ScreenshotFake(browser: (new BrowserOptions)->setSocket('invoices')->setTimeouts(load: 5000, browser: 10000));
    $image = $fake->html('invoice')->size(500, 300)->jpeg();
    $pdf = $image->pdf();
    $image->socket('images');
    $fake->assertNoPdfsCaptured();
    $fake->assertNothingCaptured();

    $pdf->paper(PaperSize::Letter)->landscape()->margins(5, 6, 7, 8)->background(false)->preferCssPageSize()->capture();
    $fake->assertPdfCaptured(fn ($source, $type, CaptureOptions $options) => $source === 'invoice'
        && $type === SourceType::Html && $options->getFormat() === OutputFormat::Pdf && $options->browser->getSocket() === 'invoices' && $options->browser->getLoadTimeout() === 5000
        && $options->browser->getBrowserTimeout() === 10000 && $options->output->getPaperWidth() === 215.9 && $options->output->getLandscape()
        && $options->output->getMargins() === ['top' => 5.0, 'right' => 6.0, 'bottom' => 7.0, 'left' => 8.0]
        && ! $options->output->getBackground() && $options->output->getPreferCssPageSize());
    $fake->assertPdfCapturedCount(1);
    $fake->assertNothingCaptured();

    $image->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options->browser->getSocket() === 'images' && $options->getFormat() === OutputFormat::Jpeg);
});

test('PDF requests and recorded assertion options are independent snapshots', function () {
    $fake = new ScreenshotFake;
    $source = $fake->html('invoice')->socket('shared');
    $first = $source->pdf()->paperSize(100, 150)->withoutSocket()->javascript()->failOnTimeout()->timeouts(browser: 2000);
    $second = $source->pdf();
    $first->capture();
    $first->paper(PaperSize::A3);
    $second->capture();

    $fake->assertPdfCaptured(function ($source, $type, $options) {
        $options->output->setPaper(PaperSize::Legal);
        $options->browser->setSocket('mutated');

        return true;
    });
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->output->getPaperWidth() === 100.0
        && $options->browser->getSocket() === null && $options->browser->getJavascript() && $options->browser->getFailOnTimeout()
        && $options->browser->getBrowserTimeout() === 2000);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->output->getPaperWidth() === 210.0
        && $options->browser->getSocket() === 'shared' && ! $options->browser->getJavascript());
});

test('one driver contract handles images and PDFs with the requested enum format', function () {
    $driver = Mockery::mock(Driver::class);
    $image = new ImageResult(imageFixture());
    $pdf = new PdfResult(pdfFixture());
    $driver->shouldReceive('capture')->once()
        ->withArgs(fn ($source, $type, CaptureOptions $options) => $source === 'invoice' && $type === SourceType::Html && $options->getFormat() === OutputFormat::Jpeg)
        ->andReturn($image);
    $driver->shouldReceive('capture')->once()
        ->withArgs(fn ($source, $type, CaptureOptions $options) => $source === 'invoice'
            && $type === SourceType::Html && $options->getFormat() === OutputFormat::Pdf && $options->output->getLandscape())
        ->andReturn($pdf);
    $pending = (new Screenshot($driver))->html('invoice')->jpeg();

    expect($pending->capture())->toBe($image)
        ->and($pending->pdf()->landscape()->capture())->toBe($pdf);
});

test('PDF results expose document bytes without image dimensions', function () {
    $result = new PdfResult(pdfFixture());

    expect($result->contents())->toStartWith('%PDF-')
        ->and($result->base64())->toBe(pdfFixture())
        ->and($result->dataUri())->toBe('data:application/pdf;base64,'.pdfFixture())
        ->and($result->mimeType())->toBe('application/pdf')
        ->and(method_exists($result, 'width'))->toBeFalse();
});

test('PDF results reject image bytes and invalid base64', function ($input) {
    expect(fn () => new PdfResult($input))->toThrow(InvalidArgumentException::class);
})->with(['', 'invalid base64!', imageFixture(), base64_encode('%PDF-1.4 truncated')]);

test('PDF fake assertions fail when no matching document was captured', function () {
    $fake = new ScreenshotFake;
    expect(fn () => $fake->assertPdfCaptured())->toThrow(AssertionFailedError::class);
    $fake->html('hello')->pdf()->capture();
    expect(fn () => $fake->assertNoPdfsCaptured())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertPdfCaptured(fn () => false))->toThrow(AssertionFailedError::class);
});

test('paper dimensions and margins are converted from millimeters to inches', function () {
    $pdf = (new PdfOptions)->setPaper(PaperSize::Letter)->setMargins(25.4, 12.7, 25.4, 12.7);
    $parameters = $pdf->toArray();

    expect($parameters['paperWidth'])->toEqualWithDelta(8.5, 0.00001)
        ->and($parameters['paperHeight'])->toEqualWithDelta(11, 0.00001)
        ->and($parameters['marginTop'])->toBe(1.0)
        ->and($parameters['marginLeft'])->toBe(0.5);
});

test('invalid print settings leave the entire PDF configuration unchanged', function ($method, $arguments) {
    $pdf = new PdfOptions;
    expect(fn () => $pdf->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
    expect($pdf)->toEqual(new PdfOptions);
})->with([
    ['setPaperSize', [0, 297]],
    ['setPaperSize', [210, INF]],
    ['setPaperSize', [NAN, 297]],
    ['setPaperSize', [10, 10]],
    ['setMargins', [10, -1, 10, 10]],
    ['setMargins', [INF, 10, 10, 10]],
    ['setMargins', [10, 105, 10, 105]],
]);

test('orientation changes cannot invalidate the printable area', function () {
    $pdf = (new PdfOptions)->setMargins(120, 10, 120, 10);

    expect(fn () => $pdf->setLandscape(true))->toThrow(InvalidArgumentException::class);
    expect($pdf->getLandscape())->toBeFalse();
});

test('image and PDF builders save their own result types without changing each other', function () {
    $fake = new ScreenshotFake;
    $image = $fake->html('invoice');
    $pdf = $image->pdf();
    $imagePath = tempnam(sys_get_temp_dir(), 'screenshot-image-');
    $pdfPath = tempnam(sys_get_temp_dir(), 'screenshot-pdf-');

    try {
        $imageResult = $image->save($imagePath);
        $pdfResult = $pdf->save($pdfPath);

        expect($image)->toBeInstanceOf(PendingImage::class)
            ->and($pdf)->toBeInstanceOf(PendingPdf::class)
            ->and($imageResult)->toBeInstanceOf(ImageResult::class)
            ->and($pdfResult)->toBeInstanceOf(PdfResult::class)
            ->and(file_get_contents($imagePath))->toBe($imageResult->contents())
            ->and(file_get_contents($pdfPath))->toBe($pdfResult->contents());
        $fake->assertCapturedCount(1);
        $fake->assertPdfCapturedCount(1);
    } finally {
        unlink($imagePath);
        unlink($pdfPath);
    }
});

test('combined layout changes validate the final paper and margins atomically', function () {
    $pdf = (new PdfOptions)->setMargins(20, 20, 20, 20);

    expect($pdf->setLayout(width: 30, height: 30, top: 0, right: 0, bottom: 0, left: 0))->toBe($pdf)
        ->and($pdf->getPaperWidth())->toBe(30.0)
        ->and($pdf->getMargins())->toBe(['top' => 0.0, 'right' => 0.0, 'bottom' => 0.0, 'left' => 0.0]);
    $before = clone $pdf;

    expect(fn () => $pdf->setLayout(width: 10, height: 10, landscape: true, top: 10))
        ->toThrow(InvalidArgumentException::class);
    expect($pdf)->toEqual($before);
});
