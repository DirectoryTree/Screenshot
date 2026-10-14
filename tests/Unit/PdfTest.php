<?php

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\PendingScreenshot;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\ScreenshotResult;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use PHPUnit\Framework\AssertionFailedError;

function pdfFixture(): string
{
    return base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf'));
}

test('PDF selection uses the same builder and preserves browser defaults', function () {
    $fake = new ScreenshotFake;
    $fake->configure(fn (PendingScreenshot $screenshot) => $screenshot->socket('invoices')->timeouts(load: 5000, browser: 10000));
    $pending = $fake->html('invoice')->size(500, 300)->jpeg();

    expect($pending->pdf())->toBe($pending);
    $fake->assertNoPdfsCaptured();
    $fake->assertNothingCaptured();

    $result = $pending->paper('letter')->landscape()->margins(5, 6, 7, 8)->background(false)->preferCssPageSize()->capture();
    expect($result)->toBeInstanceOf(PdfResult::class);
    $fake->assertPdfCaptured(fn ($source, $type, CaptureOptions $options, PdfOptions $pdf) => $source === 'invoice'
        && $type === 'html' && $options->getFormat() === 'pdf' && $options->getSocket() === 'invoices'
        && $options->getLoadTimeout() === 5000 && $options->getBrowserTimeout() === 10000
        && $pdf->getPaperWidth() === 215.9 && $pdf->getLandscape()
        && $pdf->getMargins() === ['top' => 5.0, 'right' => 6.0, 'bottom' => 7.0, 'left' => 8.0]
        && ! $pdf->getBackground() && $pdf->getPreferCssPageSize());
    $fake->assertPdfCapturedCount(1);
    $fake->assertNothingCaptured();
});

test('the last format selection controls capture without losing print settings', function () {
    $fake = new ScreenshotFake;
    $pending = $fake->html('invoice')->paper('letter')->pdf();

    expect($pending->png())->toBe($pending)
        ->and($pending->capture())->toBeInstanceOf(ScreenshotResult::class);
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getFormat() === 'png');
    expect($pending->pdf()->jpeg(90))->toBe($pending);
    $pending->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getFormat() === 'jpeg' && $options->getQuality() === 90);
    $pending->pdf()->capture();
    $fake->assertPdfCaptured(fn ($source, $type, $options, $pdf) => $pdf->getPaperWidth() === 215.9);
    $fake->assertCapturedCount(2);
    $fake->assertPdfCapturedCount(1);
});

test('global configuration applies print defaults without sharing mutable options', function () {
    $fake = new ScreenshotFake;
    $fake->configure(fn (PendingScreenshot $screenshot) => $screenshot->pdf()->paper('letter')->landscape());
    $first = $fake->html('first')->paperSize(100, 150)->withoutSocket()->javascript()->failOnTimeout()->timeouts(browser: 2000);
    $second = $fake->html('second');
    $first->capture();
    $first->paper('a3');
    $second->capture();

    $fake->assertPdfCaptured(function ($source, $type, $options, $pdf) {
        $pdf->setPaper('legal');
        $options->setSocket('mutated');

        return true;
    });
    $fake->assertPdfCaptured(fn ($source, $type, $options, $pdf) => $source === 'first'
        && $pdf->getPaperWidth() === 100.0 && $options->getSocket() === null
        && $options->getJavascript() && $options->getFailOnTimeout() && $options->getBrowserTimeout() === 2000);
    $fake->assertPdfCaptured(fn ($source, $type, $options, $pdf) => $source === 'second'
        && $pdf->getPaperWidth() === 215.9 && $pdf->getLandscape() && ! $options->getJavascript());
});

test('PDF print settings supplied to the constructor are cloned', function () {
    $fake = new ScreenshotFake;
    $options = new CaptureOptions(format: 'pdf');
    $pdf = new PdfOptions;
    $pending = new PendingScreenshot($fake, 'invoice', 'html', $options, $pdf);
    $options->setFormat('jpeg');
    $pdf->setPaper('letter');
    $pending->capture();
    $fake->assertPdfCaptured(fn ($source, $type, $options, $pdf) => $options->getFormat() === 'pdf' && $pdf->getPaperWidth() === 210.0);
});

test('image-only custom drivers still work and reject PDF conversion clearly', function () {
    $driver = Mockery::mock(Driver::class);
    $factory = new Screenshot($driver);

    expect(fn () => $factory->html('invoice')->pdf()->capture())->toThrow(LogicException::class, 'does not support PDF generation');
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
