<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('configured defaults are available before selecting an output format', function () {
    $fake = new ScreenshotFake(
        browser: (new BrowserOptions)->setSize(900, 600)->setSocket('captures')->setTimeouts(2000, 90000),
        image: (new ImageOptions)->setFormat(OutputFormat::Png, 85)->setFullPage(false),
        pdf: (new PdfOptions)->setPaper(PaperSize::Letter)->setLandscape(true),
    );

    $pending = $fake->html('source');
    $fake->assertNothingCaptured();
    $fake->assertNoPdfsCaptured();
    $pending->capture();
    $pending->jpeg()->capture();
    $pending->pdf()->capture();

    $fake->assertCaptured(fn ($source, $type, CaptureOptions $options) => $options->getFormat() === OutputFormat::Png
        && $options->browser->getWidth() === 900 && $options->browser->getSocket() === 'captures'
        && ! $options->output->getFullPage());
    $fake->assertCaptured(fn ($source, $type, CaptureOptions $options) => $options->getFormat() === OutputFormat::Jpeg
        && $options->browser->getLoadTimeout() === 2000 && $options->browser->getBrowserTimeout() === 90000
        && $options->output->getQuality() === 85);
    $fake->assertPdfCaptured(fn ($source, $type, CaptureOptions $options) => $options->browser->getWidth() === 900
        && $options->browser->getSocket() === 'captures' && $options->output->getPaperWidth() === 215.9
        && $options->output->getLandscape());
});

test('fluent settings replace defaults including false null and package default values', function () {
    $fake = new ScreenshotFake(
        browser: (new BrowserOptions)->setSize(1280, 720)->setSocket('defaults')->setJavascript(true)
            ->setFailOnTimeout(true)->setTimeouts(2000, 90000),
        image: (new ImageOptions)->setFormat(OutputFormat::Jpeg, 95)->setFullPage(false),
        pdf: (new PdfOptions)->setPaper(PaperSize::Letter)->setLandscape(true)->setMargins(20, 20, 20, 20)
            ->setPreferCssPageSize(true),
    );

    $image = $fake->html('override')->size(1024, 768)->withoutSocket()->javascript(false)
        ->failOnTimeout(false)->timeouts(load: 15000)->fullPage()->png();
    $image->jpeg(80)->capture();
    $image->pdf()->paper(PaperSize::A4)->landscape(false)->margins(1, 2, 3, 4)
        ->background(false)->preferCssPageSize(false)->capture();
    $fake->html('default')->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'override'
        && $options->browser->getWidth() === 1024 && $options->browser->getHeight() === 768
        && $options->browser->getSocket() === null && ! $options->browser->getJavascript()
        && ! $options->browser->getFailOnTimeout() && $options->browser->getLoadTimeout() === 15000
        && $options->browser->getBrowserTimeout() === 90000
        && $options->output->getQuality() === 80 && $options->output->getFullPage());
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->browser->getSocket() === null
        && ! $options->browser->getJavascript() && $options->output->getPaperWidth() === 210.0
        && ! $options->output->getLandscape() && ! $options->output->getBackground()
        && ! $options->output->getPreferCssPageSize()
        && $options->output->getMargins() === ['top' => 1.0, 'right' => 2.0, 'bottom' => 3.0, 'left' => 4.0]);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'default'
        && $options->browser->getWidth() === 1280 && $options->browser->getSocket() === 'defaults'
        && $options->output->getQuality() === 95 && ! $options->output->getFullPage());
});

test('format changes preserve current browser settings image options and JPEG quality', function () {
    $fake = new ScreenshotFake(
        browser: (new BrowserOptions)->setTimeouts(900, 3000),
        image: (new ImageOptions)->setFormat(OutputFormat::Png, 95)->setMaxSize(2000, 2000),
    );

    $fake->html('latest')->jpeg(70)->png()->jpeg()
        ->size(800, 600)->size(640, 480)->maxSize(1200, 1600)->viewport()
        ->timeouts(load: 1000)->timeouts(browser: 2000)->timeouts()->capture();
    $fake->html('default quality')->jpeg()->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'latest'
        && $options->getFormat() === OutputFormat::Jpeg && $options->output->getQuality() === 70
        && $options->browser->getWidth() === 640 && $options->browser->getHeight() === 480
        && $options->output->getMaxWidth() === 1200 && $options->output->getMaxHeight() === 1600
        && ! $options->output->getFullPage() && $options->browser->getLoadTimeout() === 1000
        && $options->browser->getBrowserTimeout() === 2000);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'default quality'
        && $options->output->getQuality() === 95);
});

test('PDF conversion copies inherited and overridden browser settings with independent print defaults', function () {
    $fake = new ScreenshotFake(
        browser: (new BrowserOptions)->setSize(900, 600)->setJavascript(true)->setSocket('shared')->setTimeouts(2000, 4000),
        image: (new ImageOptions)->setFormat(OutputFormat::Jpeg, 95)->setMaxSize(100, 100)->setFullPage(false),
        pdf: (new PdfOptions)->setPaper(PaperSize::Letter),
    );
    $image = $fake->html('invoice')->size(640, 480)->timeouts(load: 1000)->failOnTimeout();
    $first = $image->pdf()->paper(PaperSize::A5);
    $second = $image->pdf();
    $image->size(320, 240)->withoutSocket()->javascript(false);
    $first->capture();
    $second->capture();
    $image->capture();

    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 640
        && $options->browser->getHeight() === 480 && $options->browser->getJavascript()
        && $options->browser->getSocket() === 'shared' && $options->browser->getLoadTimeout() === 1000
        && $options->browser->getBrowserTimeout() === 4000 && $options->browser->getFailOnTimeout()
        && $options->output->getPaperWidth() === 148.0);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->output->getPaperWidth() === 215.9);
    $fake->assertCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 320
        && $options->browser->getSocket() === null && ! $options->browser->getJavascript()
        && $options->output->getMaxWidth() === 100 && $options->output->getQuality() === 95);
});

test('factories and fakes snapshot each set of configured defaults', function () {
    $browser = (new BrowserOptions)->setSize(900, 600);
    $image = (new ImageOptions)->setFormat(OutputFormat::Jpeg, 85);
    $pdf = (new PdfOptions)->setPaper(PaperSize::Letter);
    $factory = new Screenshot(browser: $browser, image: $image, pdf: $pdf);
    $browser->setSize(800, 600);
    $image->setFormat(OutputFormat::Png, 50);
    $pdf->setPaper(PaperSize::A5);
    $fake = ScreenshotFake::from($factory);
    $pending = $fake->html('image');
    $pending->capture();
    $pending->pdf()->capture();
    $fake->html('next')->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 900
        && $options->getFormat() === OutputFormat::Jpeg && $options->output->getQuality() === 85);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 900
        && $options->output->getPaperWidth() === 215.9);
    $fake->assertCapturedCount(2);
    $other = new ScreenshotFake;
    $other->html('default')->capture();
    $other->assertCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 1024
        && $options->getFormat() === OutputFormat::Png);
});

test('repeated captures use current settings without applying configuration again', function () {
    $fake = new ScreenshotFake(browser: (new BrowserOptions)->setSize(800, 600));
    $pending = $fake->html('image');
    $pending->capture();
    $pending->size(900, 600)->capture();
    $pending->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 800);
    $fake->assertCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 900);
    $fake->assertCapturedCount(3);
});

test('PDF layout overrides are validated against current defaults and can be corrected', function () {
    $fake = new ScreenshotFake(pdf: (new PdfOptions)->setMargins(20, 20, 20, 20));
    $pending = $fake->html('small')->pdf();

    expect(fn () => $pending->paperSize(30, 30))->toThrow(InvalidArgumentException::class);
    $fake->assertNoPdfsCaptured();
    $pending->margins(0, 0, 0, 0)->paperSize(30, 30)->capture();
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->output->getPaperWidth() === 30.0
        && $options->output->getMargins()['top'] === 0.0);
});
