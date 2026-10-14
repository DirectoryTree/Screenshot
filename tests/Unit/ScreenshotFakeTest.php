<?php

use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use PHPUnit\Framework\AssertionFailedError;

test('a fake created from another factory starts with fresh records and uses the supplied result', function () {
    $original = new ScreenshotFake;
    $original->html('previous')->capture();
    $original->close('emails');
    $result = new ImageResult(imageFixture());

    $fake = ScreenshotFake::from(factory: $original, result: $result);
    $fake->assertNothingCaptured();
    $fake->assertNothingClosed();
    expect($fake->html('next')->capture())->toBe($result);
    $fake->assertCapturedCount(1);
    $original->assertCapturedCount(1);
});

test('shutdown requests match exact keys and are separate from captures', function () {
    $fake = new ScreenshotFake;
    $fake->assertNotClosed('emails');
    $fake->close('emails');
    $fake->close('01');
    $fake->close('emails');

    $fake->assertClosed('emails');
    $fake->assertClosed('01');
    $fake->assertNotClosed('1');
    $fake->assertNotClosed('Emails');
    $fake->assertNothingCaptured();
});

test('assertClosed fails when the requested key was not closed', function () {
    $fake = new ScreenshotFake;
    $fake->close('invoices');

    expect(fn () => $fake->assertClosed('emails'))
        ->toThrow(AssertionFailedError::class, 'The expected browser [emails] was not closed.');
});

test('assertNotClosed fails when the requested key was closed', function () {
    $fake = new ScreenshotFake;
    $fake->close('emails');

    expect(fn () => $fake->assertNotClosed('emails'))
        ->toThrow(AssertionFailedError::class, 'The unexpected browser [emails] was closed.');
});

test('assertNothingClosed fails when any shutdown was requested', function () {
    $fake = new ScreenshotFake;
    $fake->close('emails');

    expect(fn () => $fake->assertNothingClosed())
        ->toThrow(AssertionFailedError::class, 'Unexpected browser shutdowns were requested.');
});

test('one capture history preserves mixed output order and filters assertion callbacks', function () {
    $fake = new class extends ScreenshotFake
    {
        public function recordedSources(): array
        {
            return array_column($this->captures, 0);
        }
    };

    $fake->html('png')->capture();
    $fake->html('first pdf')->pdf()->capture();
    $fake->html('jpeg')->jpeg()->capture();
    $fake->html('second pdf')->pdf()->capture();

    expect($fake->recordedSources())->toBe(['png', 'first pdf', 'jpeg', 'second pdf']);
    $fake->assertCapturedCount(2);
    $fake->assertPdfCapturedCount(2);
    $fake->assertCaptured(fn ($source, $type, ImageOptions $options) => $source === 'jpeg'
        && $options->getFormat() === OutputFormat::Jpeg);
    $fake->assertPdfCaptured(fn ($source, $type, PdfOptions $options) => $source === 'second pdf'
        && $options->getFormat() === OutputFormat::Pdf);
});

test('PDF records cannot satisfy image assertions', function () {
    $fake = new ScreenshotFake;
    $fake->html('pdf')->pdf()->capture();

    $fake->assertNothingCaptured();
    $fake->assertPdfCapturedCount(1);
    expect(fn () => $fake->assertCaptured())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertCapturedCount(1))->toThrow(AssertionFailedError::class);
});

test('image records cannot satisfy PDF assertions', function () {
    $fake = new ScreenshotFake;
    $fake->html('image')->capture();

    $fake->assertNoPdfsCaptured();
    $fake->assertCapturedCount(1);
    expect(fn () => $fake->assertPdfCaptured())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertPdfCapturedCount(1))->toThrow(AssertionFailedError::class);
});

test('a fresh fake clears both kinds of records without changing the original history', function () {
    $original = new ScreenshotFake;
    $original->html('image')->capture();
    $original->html('pdf')->pdf()->capture();

    $fake = ScreenshotFake::from($original);
    $fake->assertNothingCaptured();
    $fake->assertNoPdfsCaptured();
    $original->assertCapturedCount(1);
    $original->assertPdfCapturedCount(1);
});
