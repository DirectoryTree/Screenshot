<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PendingCapture;
use DirectoryTree\Screenshot\PendingImage;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\PendingSource;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('sources only copy output options when that output is selected', function () {
    $copies = (object) ['image' => 0, 'pdf' => 0];
    $image = new class($copies) extends ImageOptions
    {
        public function __construct(
            public object $copies
        ) {
            parent::__construct();
        }

        public function __clone(): void
        {
            $this->copies->image++;
        }
    };
    $pdf = new class($copies) extends PdfOptions
    {
        public function __construct(
            public object $copies
        ) {
            parent::__construct();
        }

        public function __clone(): void
        {
            $this->copies->pdf++;
        }
    };
    $fake = new ScreenshotFake(image: $image, pdf: $pdf);
    $imageCopies = $copies->image;
    $pdfCopies = $copies->pdf;
    $html = $fake->html('invoice');
    $url = $fake->url('https://example.com');
    $html->size(800, 600)->javascript()->socket('documents');

    expect($html)->toBeInstanceOf(PendingSource::class)
        ->and($copies->image)->toBe($imageCopies)
        ->and($copies->pdf)->toBe($pdfCopies)
        ->and($html->pdf())->toBeInstanceOf(PendingPdf::class)
        ->and($copies->image)->toBe($imageCopies)
        ->and($copies->pdf)->toBe($pdfCopies + 1)
        ->and($url->png())->toBeInstanceOf(PendingImage::class)
        ->and($copies->image)->toBe($imageCopies + 1)
        ->and($copies->pdf)->toBe($pdfCopies + 1)
        ->and($html->jpeg(95))->toBeInstanceOf(PendingImage::class)
        ->and($copies->image)->toBe($imageCopies + 2)
        ->and($copies->pdf)->toBe($pdfCopies + 1);
    $fake->assertNothingCaptured();
    $fake->assertNoPdfsCaptured();
});

test('output selection snapshots browser settings without sharing image or PDF mutations', function () {
    $fake = new ScreenshotFake(
        image: (new ImageOptions)->setFormat(OutputFormat::Jpeg, 85),
        pdf: (new PdfOptions)->setPaper(PaperSize::Letter),
    );
    $source = $fake->html('invoice')->size(800, 600)->socket('documents')->javascript()->failOnTimeout()
        ->timeouts(load: 1000)->timeouts(browser: 2000)->timeouts();
    $png = $source->png()->maxSize(500, 400)->viewport();
    $jpeg = $source->jpeg(90);
    $pdf = $source->pdf()->paper(PaperSize::A5);
    $source->size(320, 240)->withoutSocket()->javascript(false)->failOnTimeout(false);
    $png->size(640, 480)->capture();
    $jpeg->capture();
    $pdf->capture();
    $source->pdf()->capture();
    $source->jpeg()->capture();

    $fake->assertCaptured(fn ($contents, $type, $options) => $contents === 'invoice' && $type === SourceType::Html
        && $options->getFormat() === OutputFormat::Png && $options->browser->getWidth() === 640
        && $options->output->getMaxWidth() === 500 && ! $options->output->getFullPage());
    $fake->assertCaptured(fn ($contents, $type, $options) => $options->getFormat() === OutputFormat::Jpeg
        && $options->browser->getWidth() === 800 && $options->output->getQuality() === 90
        && $options->output->getMaxWidth() === 2048 && $options->output->getFullPage());
    $fake->assertPdfCaptured(fn ($contents, $type, $options) => $options->browser->getWidth() === 800
        && $options->browser->getSocket() === 'documents' && $options->browser->getJavascript()
        && $options->browser->getFailOnTimeout() && $options->browser->getLoadTimeout() === 1000
        && $options->browser->getBrowserTimeout() === 2000 && $options->output->getPaperWidth() === 148.0);
    $fake->assertPdfCaptured(fn ($contents, $type, $options) => $options->browser->getWidth() === 320
        && $options->browser->getSocket() === null && ! $options->browser->getJavascript()
        && ! $options->browser->getFailOnTimeout() && $options->output->getPaperWidth() === 215.9);
    $fake->assertCaptured(fn ($contents, $type, $options) => $options->browser->getWidth() === 320
        && $options->getFormat() === OutputFormat::Jpeg && $options->output->getQuality() === 85);
});

test('directly constructed source requests snapshot their browser settings', function () {
    $fake = new ScreenshotFake;
    $browser = (new BrowserOptions)->setSize(800, 600);
    $source = new PendingSource($fake, new PendingCapture($fake, 'invoice', SourceType::Html), $browser);
    $browser->setSize(320, 240);
    $source->pdf()->capture();

    $fake->assertPdfCaptured(fn ($contents, $type, $options) => $options->browser->getWidth() === 800
        && $options->browser->getHeight() === 600);
});

test('invalid source browser changes preserve current settings', function ($method, $arguments) {
    $fake = new ScreenshotFake;
    $source = $fake->html('invoice')->size(800, 600)->timeouts(1000, 2000)->socket('documents');

    expect(fn () => $source->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
    $source->pdf()->capture();

    $fake->assertPdfCaptured(fn ($contents, $type, $options) => $options->browser->getWidth() === 800
        && $options->browser->getHeight() === 600 && $options->browser->getLoadTimeout() === 1000
        && $options->browser->getBrowserTimeout() === 2000 && $options->browser->getSocket() === 'documents');
})->with([
    'size' => ['size', [400, 0]],
    'load timeout' => ['timeouts', ['load' => 0, 'browser' => 3000]],
    'browser timeout' => ['timeouts', ['load' => 3000, 'browser' => 0]],
    'socket' => ['socket', [' ']],
]);
