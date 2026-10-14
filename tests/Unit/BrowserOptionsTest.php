<?php

use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\OutputFormat;
use DirectoryTree\Screenshot\PaperSize;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('both output options validate shared browser settings when set', function ($class, $method, $arguments) {
    expect(fn () => $class::make()->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([ImageOptions::class, PdfOptions::class])->with([
    'width' => ['setSize', [0, 768]],
    'height' => ['setSize', [1024, 20000]],
    'browser timeout' => ['setBrowserTimeout', [0]],
    'load timeout' => ['setLoadTimeout', [-1]],
    'socket' => ['setSocket', [' ']],
]);

test('PDF options reject invalid browser changes without modifying either setting', function () {
    $options = PdfOptions::make()->setSize(400, 300)->setTimeouts(load: 500, browser: 1000);

    expect(fn () => $options->setSize(800, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => $options->setTimeouts(load: 100, browser: 0))->toThrow(InvalidArgumentException::class);
    expect($options->getWidth())->toBe(400)->and($options->getHeight())->toBe(300)
        ->and($options->getLoadTimeout())->toBe(500)->and($options->getBrowserTimeout())->toBe(1000);
});

test('PDF captures retain shared settings while applying print overrides to one options object', function () {
    $fake = new ScreenshotFake(options: ImageOptions::make()->setSize(900, 600)->setJavascript(true));
    $fake->configure(OutputFormat::Pdf, fn (PendingPdf $pdf) => $pdf
        ->size(800, 500)->timeouts(load: 2000, browser: 4000)->socket('documents')->paper(PaperSize::Letter));
    $image = $fake->html('invoice')->size(640, 480)->timeouts(load: 1000)
        ->javascript(false)->failOnTimeout()->maxSize(100, 100)->viewport()->jpeg(95);
    $pdf = $image->pdf();
    $image->size(320, 240);
    $pdf->paper(PaperSize::A5)->capture();

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
    $options = ImageOptions::make();
    expect(fn () => $options->setFormat(OutputFormat::Pdf, 95))->toThrow(InvalidArgumentException::class);
    expect($options->getFormat())->toBe(OutputFormat::Png)->and($options->getQuality())->toBe(80);
});

test('option factories return independent instances with browser defaults', function ($class) {
    $first = $class::make()->setSize(400, 300)->setSocket('first')->setJavascript(true);
    $second = $class::make();

    expect($first)->toBeInstanceOf($class)->not->toBe($second)
        ->and($second->getWidth())->toBe(1024)->and($second->getHeight())->toBe(768)
        ->and($second->getBrowserTimeout())->toBe(60000)->and($second->getLoadTimeout())->toBe(15000)
        ->and($second->getSocket())->toBeNull()->and($second->getJavascript())->toBeFalse()
        ->and($second->getFailOnTimeout())->toBeFalse();
})->with([ImageOptions::class, PdfOptions::class]);

test('options require factory construction', function ($class) {
    expect(fn () => new $class)->toThrow(Error::class, 'protected');
})->with([ImageOptions::class, PdfOptions::class]);

class CustomImageOptions extends ImageOptions
{
    protected int $width = 1200;
}

test('the inherited factory respects subclass defaults and validated setters', function () {
    $options = CustomImageOptions::make();

    expect($options)->toBeInstanceOf(CustomImageOptions::class)
        ->and($options->getWidth())->toBe(1200)
        ->and($options->setSize(800, 600))->toBe($options);
    expect(fn () => $options->setSize(0, 600))->toThrow(InvalidArgumentException::class);
});
