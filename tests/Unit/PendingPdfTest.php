<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PendingCapture;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('directly constructed PDF builders own their browser and print settings', function () {
    $fake = new ScreenshotFake;
    $options = new CaptureOptions(BrowserOptions::make()->setSocket('default'), PdfOptions::make());
    $capture = new PendingCapture($fake, 'invoice', SourceType::Html);
    $first = new PendingPdf($capture, $options);
    $second = new PendingPdf($capture, $options);
    $options->browser->setSocket('changed');
    $options->output->setPaper(PaperSize::Legal);

    expect($first->size(800, 600))->toBe($first)
        ->and($first->timeouts(load: 1000))->toBe($first)
        ->and($first->timeouts(browser: 2000))->toBe($first)
        ->and($first->timeouts())->toBe($first)
        ->and($first->javascript())->toBe($first)
        ->and($first->failOnTimeout())->toBe($first)
        ->and($first->socket('documents'))->toBe($first);
    $first->paper(PaperSize::Letter)->capture();
    $first->withoutSocket()->javascript(false)->failOnTimeout(false)->capture();
    $second->capture();

    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 800
        && $options->browser->getHeight() === 600 && $options->browser->getLoadTimeout() === 1000
        && $options->browser->getBrowserTimeout() === 2000 && $options->browser->getJavascript()
        && $options->browser->getFailOnTimeout() && $options->browser->getSocket() === 'documents'
        && $options->output->getPaperWidth() === 215.9);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 800
        && ! $options->browser->getJavascript() && ! $options->browser->getFailOnTimeout()
        && $options->browser->getSocket() === null);
    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 1024
        && $options->browser->getSocket() === 'default' && $options->output->getPaperWidth() === 210.0);
    $fake->assertPdfCapturedCount(3);
});

test('invalid PDF browser changes leave current settings intact', function ($method, $arguments) {
    $fake = new ScreenshotFake;
    $pending = $fake->html('invoice')->pdf()->size(800, 600)->timeouts(load: 1000, browser: 2000)->socket('documents');

    expect(fn () => $pending->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
    $fake->assertNoPdfsCaptured();
    $pending->capture();

    $fake->assertPdfCaptured(fn ($source, $type, $options) => $options->browser->getWidth() === 800
        && $options->browser->getHeight() === 600 && $options->browser->getLoadTimeout() === 1000
        && $options->browser->getBrowserTimeout() === 2000 && $options->browser->getSocket() === 'documents');
})->with([
    'size' => ['size', [400, 0]],
    'load timeout' => ['timeouts', ['load' => 0, 'browser' => 3000]],
    'browser timeout' => ['timeouts', ['load' => 3000, 'browser' => 0]],
    'socket' => ['socket', [' ']],
]);
