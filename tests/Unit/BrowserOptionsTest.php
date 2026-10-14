<?php

use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\OutputFormat;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('both output options validate shared browser settings on construction', function ($class, $settings) {
    expect(fn () => new $class(...$settings))->toThrow(InvalidArgumentException::class);
})->with([ImageOptions::class, PdfOptions::class])->with([
    'width' => [['width' => 0]],
    'height' => [['height' => 20000]],
    'browser timeout' => [['browserTimeout' => 0]],
    'load timeout' => [['loadTimeout' => -1]],
    'socket' => [['socket' => ' ']],
]);

test('PDF options reject invalid browser changes without modifying either setting', function () {
    $options = new PdfOptions(width: 400, height: 300, loadTimeout: 500, browserTimeout: 1000);

    expect(fn () => $options->setSize(800, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => $options->setTimeouts(load: 100, browser: 0))->toThrow(InvalidArgumentException::class);
    expect($options->getWidth())->toBe(400)->and($options->getHeight())->toBe(300)
        ->and($options->getLoadTimeout())->toBe(500)->and($options->getBrowserTimeout())->toBe(1000);
});

test('PDF captures retain shared settings while applying print overrides to one options object', function () {
    $fake = new ScreenshotFake(options: new ImageOptions(width: 900, height: 600, javascript: true));
    $fake->configure(OutputFormat::Pdf, fn (PendingPdf $pdf) => $pdf
        ->size(800, 500)->timeouts(load: 2000, browser: 4000)->socket('documents')->paper('letter'));
    $image = $fake->html('invoice')->size(640, 480)->timeouts(load: 1000)
        ->javascript(false)->failOnTimeout()->maxSize(100, 100)->viewport()->jpeg(95);
    $pdf = $image->pdf();
    $image->size(320, 240);
    $pdf->paper('a5')->capture();

    $fake->assertPdfCaptured(function ($source, $type, PdfOptions $options) {
        expect(func_num_args())->toBe(3)
            ->and($options->getFormat())->toBe(OutputFormat::Pdf)
            ->and($options->getWidth())->toBe(640)->and($options->getHeight())->toBe(480)
            ->and($options->getLoadTimeout())->toBe(1000)->and($options->getBrowserTimeout())->toBe(4000)
            ->and($options->getSocket())->toBe('documents')->and($options->getJavascript())->toBeFalse()
            ->and($options->getFailOnTimeout())->toBeTrue()->and($options->getPaperWidth())->toBe(148.0)
            ->and(method_exists($options, 'getQuality'))->toBeFalse();

        return true;
    });
});

test('image options cannot switch to PDF or partially update quality', function () {
    $options = new ImageOptions;
    expect(fn () => $options->setFormat(OutputFormat::Pdf, 95))->toThrow(InvalidArgumentException::class);
    expect($options->getFormat())->toBe(OutputFormat::Png)->and($options->getQuality())->toBe(80);
});
