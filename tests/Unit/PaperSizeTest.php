<?php

use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('standard paper sizes reach the driver with their dimensions in millimeters', function ($size, $width, $height) {
    $fake = new ScreenshotFake;
    $fake->html('invoice')->pdf()->paperSize(100, 150)->paper($size)->capture();

    $fake->assertPdfCaptured(fn ($source, $type, PdfOptions $options) => $options->getPaperWidth() === $width && $options->getPaperHeight() === $height
    );
})->with([
    'A3' => [PaperSize::A3, 297.0, 420.0],
    'A4' => [PaperSize::A4, 210.0, 297.0],
    'A5' => [PaperSize::A5, 148.0, 210.0],
    'Letter' => [PaperSize::Letter, 215.9, 279.4],
    'Legal' => [PaperSize::Legal, 215.9, 355.6],
]);

test('selecting a smaller paper cannot invalidate the printable area', function () {
    $options = PdfOptions::make()->setMargins(10, 80, 10, 80);
    $original = clone $options;

    expect(fn () => $options->setPaper(PaperSize::A5))->toThrow(InvalidArgumentException::class);
    expect($options)->toEqual($original);
});

test('paper requires an enum before invoking the driver', function () {
    $fake = new ScreenshotFake;

    expect(fn () => $fake->html('invoice')->pdf()->paper('a4')->capture())->toThrow(TypeError::class);
    $fake->assertNoPdfsCaptured();
});
