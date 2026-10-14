<?php

use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\OutputFormat;
use DirectoryTree\Screenshot\PaperSize;
use DirectoryTree\Screenshot\PendingImage;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\SourceType;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('only the selected format callback runs with its matching builder', function () {
    $fake = new ScreenshotFake;
    $calls = [];
    $fake->configure(OutputFormat::Png, function (PendingImage $image) use (&$calls) {
        $calls[] = 'png';
        $image->size(800, 600)->socket('images');
    });
    $fake->configure(OutputFormat::Jpeg, function (PendingImage $image) use (&$calls) {
        $calls[] = 'jpeg';
        $image->size(900, 600)->jpeg(85);
    });
    $fake->configure(OutputFormat::Pdf, function (PendingPdf $pdf) use (&$calls) {
        $calls[] = 'pdf';
        $pdf->paper(PaperSize::Letter)->landscape()->socket('documents');
    });

    $image = $fake->html('source');
    $pdf = $image->pdf();
    expect($calls)->toBe([]);
    $pdf->capture();
    expect($calls)->toBe(['pdf']);
    $image->jpeg()->capture();
    $image->png()->capture();
    expect($calls)->toBe(['pdf', 'jpeg', 'png']);
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getFormat() === OutputFormat::Png
        && $options->getWidth() === 800 && $options->getSocket() === 'images');
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getFormat() === OutputFormat::Jpeg
        && $options->getWidth() === 900 && $options->getQuality() === 85 && $options->getSocket() === null);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->getSocket() === 'documents'
        && $options->getWidth() === 1024 && $options->getPaperWidth() === 215.9 && $options->getLandscape());
});

test('explicit image options override format defaults before or after format selection', function ($before) {
    $fake = new ScreenshotFake;
    $fake->configure(OutputFormat::Jpeg, fn (PendingImage $image) => $image
        ->size(1280, 720)->socket('defaults')->timeouts(load: 2000, browser: 90000)->jpeg(85));
    $image = $fake->html('override');
    if (! $before) {
        $image->jpeg();
    }
    $image->size(1024, 768)->withoutSocket()->timeouts(load: 15000);
    if ($before) {
        $image->jpeg();
    }
    $image->capture();
    $fake->url('https://example.com')->jpeg()->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'override'
        && $options->getWidth() === 1024 && $options->getHeight() === 768 && $options->getSocket() === null
        && $options->getLoadTimeout() === 15000 && $options->getBrowserTimeout() === 90000 && $options->getQuality() === 85);
    $fake->assertCaptured(fn ($source, $type, $options) => $type === SourceType::Url
        && $options->getWidth() === 1280 && $options->getSocket() === 'defaults');
})->with([true, false]);

test('explicit JPEG quality wins while omitted quality uses format defaults', function () {
    $fake = new ScreenshotFake;
    $fake->configure(OutputFormat::Jpeg, fn (PendingImage $image) => $image->jpeg(95));
    $fake->html('default')->jpeg()->capture();
    $fake->html('explicit')->jpeg(80)->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'default' && $options->getQuality() === 95);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'explicit' && $options->getQuality() === 80);
});

test('PDF defaults respect explicit browser and print options without image default leakage', function () {
    $fake = new ScreenshotFake;
    $fake->configure(OutputFormat::Png, fn () => throw new RuntimeException('image callback must not run'));
    $fake->configure(OutputFormat::Pdf, fn (PendingPdf $pdf) => $pdf
        ->paper(PaperSize::Letter)->landscape()->margins(20, 20, 20, 20)->socket('pdfs')->javascript()->timeouts(browser: 80000));
    $image = $fake->html('invoice')->withoutSocket()->javascript(false)->timeouts(load: 3000);
    $pdf = $image->pdf()->paper(PaperSize::A4)->landscape(false)->margins(10, 10, 10, 10);
    $image->socket('later');
    $pdf->capture();

    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->getSocket() === null
        && ! $options->getJavascript() && $options->getLoadTimeout() === 3000 && $options->getBrowserTimeout() === 80000
        && $options->getPaperWidth() === 210.0 && ! $options->getLandscape() && $options->getMargins()['top'] === 10.0);
});

test('callbacks run once per capture and do not mutate pending state', function () {
    $fake = new ScreenshotFake;
    $width = 800;
    $calls = 0;
    $fake->configure(OutputFormat::Png, function (PendingImage $image) use (&$width, &$calls) {
        $calls++;
        $image->size($width, 600);
    });
    $pending = $fake->html('image');
    expect($calls)->toBe(0);
    $pending->capture();
    $width = 900;
    $pending->capture();
    expect($calls)->toBe(2);
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getWidth() === 800);
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getWidth() === 900);
});

test('format callbacks can be replaced or cleared independently of existing pending captures', function () {
    $fake = new ScreenshotFake;
    $fake->configure(OutputFormat::Png, fn (PendingImage $image) => $image->size(800, 600));
    $fake->configure(OutputFormat::Pdf, fn (PendingPdf $pdf) => $pdf->paper(PaperSize::Letter));
    $first = $fake->html('first');
    $fake->configure(OutputFormat::Png, fn (PendingImage $image) => $image->size(900, 600));
    $second = $fake->html('second');
    $fake->configure(OutputFormat::Png, null);
    $first->capture();
    $second->capture();
    $fake->html('third')->capture();
    $fake->html('pdf')->pdf()->capture();
    $fake->configure(OutputFormat::Pdf, null);
    $first->pdf()->capture();
    $fake->html('cleared')->pdf()->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'first' && $options->getWidth() === 800);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second' && $options->getWidth() === 900);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'third' && $options->getWidth() === 1024);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $source === 'pdf' && $options->getPaperWidth() === 215.9);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $source === 'first' && $options->getPaperWidth() === 215.9);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $source === 'cleared' && $options->getPaperWidth() === 210.0);
});

test('failed PDF configuration does not capture or contaminate a subsequent attempt', function () {
    $fake = new ScreenshotFake;
    $fail = true;
    $fake->configure(OutputFormat::Pdf, function (PendingPdf $pdf) use (&$fail) {
        if ($fail) {
            $pdf->paper(PaperSize::Letter)->margins(-1);
        }
    });
    $pending = $fake->html('invoice')->pdf();
    expect(fn () => $pending->capture())->toThrow(InvalidArgumentException::class);
    $fake->assertNoPdfsCaptured();
    $fail = false;
    $pending->capture();
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->getPaperWidth() === 210.0);
});

test('fakes preserve all format callbacks and independent baseline options', function () {
    $options = ImageOptions::make()->setSize(900, 768);
    $factory = new Screenshot(options: $options);
    $factory->configure(OutputFormat::Jpeg, fn (PendingImage $image) => $image->jpeg(85));
    $factory->configure(OutputFormat::Pdf, fn (PendingPdf $pdf) => $pdf->paper(PaperSize::Letter));
    $fake = ScreenshotFake::from($factory);
    $options->setSize(800, 600);
    $factory->configure(OutputFormat::Jpeg, null)->configure(OutputFormat::Pdf, null);
    $fake->html('image')->jpeg()->capture();
    $fake->html('document')->pdf()->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getWidth() === 900 && $options->getQuality() === 85);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->getWidth() === 900 && $options->getPaperWidth() === 215.9);
    $other = new ScreenshotFake;
    $other->html('default')->capture();
    $other->assertCaptured(fn ($source, $type, $options) => $options->getWidth() === 1024);
});

test('explicit print layout replaces defaults atomically', function () {
    $fake = new ScreenshotFake;
    $fake->configure(OutputFormat::Pdf, fn (PendingPdf $pdf) => $pdf->margins(20, 20, 20, 20));
    $fake->html('small')->pdf()->paperSize(30, 30)->margins(0, 0, 0, 0)->capture();
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->getPaperWidth() === 30.0
        && $options->getPaperHeight() === 30.0 && $options->getMargins()['top'] === 0.0);
});

test('the latest explicit settings survive repeated format changes', function () {
    $fake = new ScreenshotFake;
    $fake->configure(OutputFormat::Jpeg, fn (PendingImage $image) => $image
        ->maxSize(2000, 2000)->fullPage()->jpeg(95)->timeouts(load: 900, browser: 3000));

    $fake->html('latest')->jpeg(70)->png()->jpeg()
        ->size(800, 600)->size(640, 480)->maxSize(1200, 1600)->viewport()
        ->timeouts(load: 1000)->timeouts(browser: 2000)->timeouts()->capture();

    $fake->assertCaptured(fn ($source, $type, ImageOptions $options) => $options->getFormat() === OutputFormat::Jpeg
        && $options->getQuality() === 70 && $options->getWidth() === 640 && $options->getHeight() === 480
        && $options->getMaxWidth() === 1200 && $options->getMaxHeight() === 1600 && ! $options->getFullPage()
        && $options->getLoadTimeout() === 1000 && $options->getBrowserTimeout() === 2000);
});

test('invalid combined PDF defaults fail before capture and leave the pending capture reusable', function () {
    $fake = new ScreenshotFake;
    $largeMargins = true;
    $fake->configure(OutputFormat::Pdf, function (PendingPdf $pdf) use (&$largeMargins) {
        if ($largeMargins) {
            $pdf->margins(20, 20, 20, 20);
        }
    });
    $pending = $fake->html('small')->pdf()->paperSize(30, 30);

    expect(fn () => $pending->capture())->toThrow(InvalidArgumentException::class);
    $fake->assertNoPdfsCaptured();

    $largeMargins = false;
    $pending->capture();
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->getPaperWidth() === 30.0
        && $options->getMargins()['top'] === 10.0);
});

test('explicit PDF booleans and each margin win over defaults', function () {
    $fake = new ScreenshotFake;
    $fake->configure(OutputFormat::Pdf, fn (PendingPdf $pdf) => $pdf
        ->background()->preferCssPageSize()->margins(20, 20, 20, 20));
    $fake->html('document')->pdf()->background(false)->preferCssPageSize(false)
        ->margins(top: 1, right: 2, bottom: 3, left: 4)->capture();

    $fake->assertPdfCaptured(fn ($source, $type, $options) => ! $options->getBackground()
        && ! $options->getPreferCssPageSize()
        && $options->getMargins() === ['top' => 1.0, 'right' => 2.0, 'bottom' => 3.0, 'left' => 4.0]);
});
