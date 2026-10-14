<?php

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\PendingImage;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\ScreenshotResult;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use PHPUnit\Framework\AssertionFailedError;

function pdfFixture(): string
{
    return base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf'));
}

test('PDF conversion snapshots browser defaults without capturing or changing the source', function () {
    $fake = new ScreenshotFake;
    $fake->configure(fn (PendingImage $screenshot) => $screenshot->socket('invoices')->timeouts(load: 5000, browser: 10000));
    $image = $fake->html('invoice')->size(500, 300)->jpeg();
    $pdf = $image->pdf();
    $image->socket('images');
    $fake->assertNoPdfsCaptured();
    $fake->assertNothingCaptured();

    $pdf->paper('letter')->landscape()->margins(5, 6, 7, 8)->background(false)->preferCssPageSize()->capture();
    $fake->assertPdfCaptured(fn ($source, $type, ImageOptions $options, PdfOptions $pdf) => $source === 'invoice'
        && $type === 'html' && $options->getSocket() === 'invoices' && $options->getLoadTimeout() === 5000
        && $options->getBrowserTimeout() === 10000 && $pdf->getPaperWidth() === 215.9 && $pdf->getLandscape()
        && $pdf->getMargins() === ['top' => 5.0, 'right' => 6.0, 'bottom' => 7.0, 'left' => 8.0]
        && ! $pdf->getBackground() && $pdf->getPreferCssPageSize());
    $fake->assertPdfCapturedCount(1);
    $fake->assertNothingCaptured();

    $image->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getSocket() === 'images' && $options->getFormat() === 'jpeg');
});

test('PDF requests and recorded assertion options are independent snapshots', function () {
    $fake = new ScreenshotFake;
    $source = $fake->html('invoice')->socket('shared');
    $first = $source->pdf()->paperSize(100, 150)->withoutSocket()->javascript()->failOnTimeout()->timeouts(browser: 2000);
    $second = $source->pdf();
    $first->capture();
    $first->paper('a3');
    $second->capture();

    $fake->assertPdfCaptured(function ($source, $type, $options, $pdf) {
        $pdf->setPaper('legal');
        $options->setSocket('mutated');

        return true;
    });
    $fake->assertPdfCaptured(fn ($source, $type, $options, $pdf) => $pdf->getPaperWidth() === 100.0
        && $options->getSocket() === null && $options->getJavascript() && $options->getFailOnTimeout()
        && $options->getBrowserTimeout() === 2000);
    $fake->assertPdfCaptured(fn ($source, $type, $options, $pdf) => $pdf->getPaperWidth() === 210.0
        && $options->getSocket() === 'shared' && ! $options->getJavascript());
});

test('image-only custom drivers still work and reject PDF conversion clearly', function () {
    $driver = Mockery::mock(Driver::class);
    $factory = new Screenshot($driver);

    expect(fn () => $factory->html('invoice')->pdf())->toThrow(LogicException::class, 'does not support PDF generation');
    $driver->shouldNotReceive('capture');
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
    $options = (new PdfOptions)->setPaper('Letter')->setMargins(25.4, 12.7, 25.4, 12.7);
    $parameters = $options->toArray();

    expect($parameters['paperWidth'])->toEqualWithDelta(8.5, 0.00001)
        ->and($parameters['paperHeight'])->toEqualWithDelta(11, 0.00001)
        ->and($parameters['marginTop'])->toBe(1.0)
        ->and($parameters['marginLeft'])->toBe(0.5);
});

test('invalid print settings leave the entire PDF configuration unchanged', function ($method, $arguments) {
    $options = new PdfOptions;
    expect(fn () => $options->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
    expect($options)->toEqual(new PdfOptions);
})->with([
    ['setPaper', ['unknown']],
    ['setPaperSize', [0, 297]],
    ['setPaperSize', [210, INF]],
    ['setPaperSize', [NAN, 297]],
    ['setPaperSize', [10, 10]],
    ['setMargins', [10, -1, 10, 10]],
    ['setMargins', [INF, 10, 10, 10]],
    ['setMargins', [10, 105, 10, 105]],
]);

test('orientation changes cannot invalidate the printable area', function () {
    $options = new PdfOptions(marginTop: 120, marginBottom: 120);

    expect(fn () => $options->setLandscape(true))->toThrow(InvalidArgumentException::class);
    expect($options->getLandscape())->toBeFalse();
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
            ->and($imageResult)->toBeInstanceOf(ScreenshotResult::class)
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
