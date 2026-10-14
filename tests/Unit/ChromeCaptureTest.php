<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\Drivers\Chrome\ImageCapture;
use DirectoryTree\Screenshot\Drivers\Chrome\PdfCapture;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use HeadlessChromium\Clip;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\PagePdf;
use HeadlessChromium\PageUtils\PageScreenshot;

test('image captures use the configured bounds timeout format and quality', function ($format, $fullPage, $quality) {
    $page = Mockery::mock(Page::class);
    $image = Mockery::mock(PageScreenshot::class);
    $browser = BrowserOptions::make()->setSize(640, 480)->setTimeouts(load: 15000, browser: 1234);
    $options = ImageOptions::make()->setMaxSize(500, 600)->setFormat($format, $quality)->setFullPage($fullPage);

    if ($fullPage) {
        $page->shouldReceive('getFullPageClip')->once()->with(1234)->andReturn(new Clip(0, 0, 800, 2000));
    } else {
        $page->shouldNotReceive('getFullPageClip');
    }

    $page->shouldNotReceive('pdf');
    $page->shouldReceive('screenshot')->once()->withArgs(function ($parameters) use ($format, $fullPage, $quality) {
        expect($parameters['format'])->toBe($format->value)
            ->and($parameters['captureBeyondViewport'])->toBeTrue()
            ->and($parameters['clip']->getWidth())->toEqual(500)
            ->and($parameters['clip']->getHeight())->toEqual($fullPage ? 600 : 480);

        if ($format === OutputFormat::Jpeg) {
            expect($parameters['quality'])->toBe($quality);
        } else {
            expect($parameters)->not->toHaveKey('quality');
        }

        return true;
    })->andReturn($image);
    $image->shouldReceive('getBase64')->once()->with(1234)->andReturn(imageFixture());

    expect((new ImageCapture($browser, $options))->capture($page))->toBeInstanceOf(ImageResult::class);
})->with([
    'PNG' => OutputFormat::Png,
    'JPEG' => OutputFormat::Jpeg,
])->with(['full page' => true, 'viewport' => false])->with([0, 92, 100]);

test('the PDF strategy prints its layout without screenshot clipping', function () {
    $page = Mockery::mock(Page::class);
    $document = Mockery::mock(PagePdf::class);
    $browser = BrowserOptions::make()->setTimeouts(load: 15000, browser: 4321);
    $options = PdfOptions::make()->setPaper(PaperSize::Letter)
        ->setLandscape(true)->setMargins(25.4, 12.7, 25.4, 12.7)
        ->setBackground(false)->setPreferCssPageSize(true);

    $page->shouldNotReceive('getFullPageClip');
    $page->shouldNotReceive('screenshot');
    $page->shouldReceive('pdf')->once()->with([
        'paperWidth' => 215.9 / 25.4,
        'paperHeight' => 279.4 / 25.4,
        'marginTop' => 1.0,
        'marginRight' => 0.5,
        'marginBottom' => 1.0,
        'marginLeft' => 0.5,
        'landscape' => true,
        'printBackground' => false,
        'preferCSSPageSize' => true,
    ])->andReturn($document);
    $document->shouldReceive('getBase64')->once()->with(4321)
        ->andReturn(base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf')));

    expect((new PdfCapture($browser, $options))->capture($page))->toBeInstanceOf(PdfResult::class);
});
