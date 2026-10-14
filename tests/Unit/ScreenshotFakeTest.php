<?php

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageResult;
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

test('capture assertions inspect mixed outputs in capture order', function () {
    $fake = new ScreenshotFake;
    $sources = [];

    $fake->html('png')->capture();
    $fake->html('first pdf')
        ->pdf()
        ->capture();
    $fake->html('jpeg')
        ->jpeg()
        ->capture();
    $fake->html('second pdf')
        ->pdf()
        ->capture();

    $fake->assertCapturedCount(4);
    $fake->assertCaptured(function ($source, $type, CaptureOptions $options) use (&$sources) {
        $sources[] = $source;

        return $source === 'second pdf' && $options->format() === OutputFormat::Pdf;
    });

    expect($sources)->toBe(['png', 'first pdf', 'jpeg', 'second pdf']);

    $fake->assertCaptured(
        fn ($source, $type, CaptureOptions $options) => $source === 'jpeg'
            && $options->format() === OutputFormat::Jpeg,
    );
});

test('capture assertions support every output format', function (OutputFormat $format) {
    $fake = new ScreenshotFake;

    $source = $fake->html('invoice');

    (match ($format) {
        OutputFormat::Png => $source->png(),
        OutputFormat::Jpeg => $source->jpeg(),
        OutputFormat::Pdf => $source->pdf(),
    })->capture();

    $fake->assertCaptured();
    $fake->assertCapturedCount(1);
    $fake->assertCaptured(
        fn ($source, $type, CaptureOptions $options) => $source === 'invoice'
            && $type === SourceType::Html
            && $options->format() === $format,
    );

    expect(fn () => $fake->assertNothingCaptured())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertCapturedCount(0))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertCapturedCount(2))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertCaptured(fn () => false))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertCaptured(
        fn ($source, $type, CaptureOptions $options) => $options->format() !== $format,
    ))->toThrow(AssertionFailedError::class);
})->with(OutputFormat::cases());

test('an empty fake has no captures and cannot satisfy a capture assertion', function () {
    $fake = new ScreenshotFake;
    $calls = 0;

    $fake->assertNothingCaptured();
    $fake->assertCapturedCount(0);

    expect(fn () => $fake->assertCaptured())
        ->toThrow(AssertionFailedError::class, 'The expected capture was not recorded.');
    expect(fn () => $fake->assertCaptured(function () use (&$calls) {
        $calls++;

        return true;
    }))->toThrow(AssertionFailedError::class);
    expect($calls)->toBe(0);
});

test('assertion callbacks cannot mutate recorded browser or output settings', function (OutputFormat $format) {
    $fake = new ScreenshotFake;
    $source = $fake->html('invoice');

    (match ($format) {
        OutputFormat::Png => $source->png(),
        OutputFormat::Jpeg => $source->jpeg(),
        OutputFormat::Pdf => $source->pdf(),
    })->capture();

    $fake->assertCaptured(function ($source, $type, CaptureOptions $options) {
        $options->browser()->setSize(320, 240);

        if ($options->format() === OutputFormat::Pdf) {
            $options->output()->setLandscape(true);
        } else {
            $options->output()->setFormat(OutputFormat::Jpeg, 20);
        }

        return true;
    });
    $fake->assertCaptured(
        fn ($source, $type, CaptureOptions $options) => $options->browser()->getWidth() === 1024
            && $options->format() === $format
            && ($format === OutputFormat::Pdf
                ? ! $options->output()->getLandscape()
                : $options->output()->getQuality() === 80),
    );
})->with(OutputFormat::cases());

test('failed matches cannot mutate records before a later assertion', function () {
    $fake = new ScreenshotFake;

    $fake->html('invoice')->capture();

    expect(fn () => $fake->assertCaptured(function ($source, $type, CaptureOptions $options) {
        $options->browser()->setSize(320, 240);
        $options->output()->setFormat(OutputFormat::Jpeg, 20);

        return false;
    }))->toThrow(AssertionFailedError::class);
    $fake->assertCaptured(
        fn ($source, $type, CaptureOptions $options) => $options->browser()->getWidth() === 1024
            && $options->format() === OutputFormat::Png
            && $options->output()->getQuality() === 80,
    );
});

test('a fresh fake clears both kinds of records without changing the original history', function () {
    $original = new ScreenshotFake;

    $original->html('image')->capture();
    $original->html('pdf')
        ->pdf()
        ->capture();

    $fake = ScreenshotFake::from($original);

    $fake->assertNothingCaptured();
    $original->assertCapturedCount(2);
});
