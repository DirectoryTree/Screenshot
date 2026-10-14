<?php

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\PendingScreenshot;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('capture options override configured defaults without leaking into other captures', function () {
    $fake = new ScreenshotFake;
    $fake->configure(fn (PendingScreenshot $screenshot) => $screenshot
        ->size(1280, 720)->jpeg(85)->socket('emails'));

    $fake->html('override')->size(640, 480)->png()->withoutSocket()->capture();
    $fake->url('https://example.com')->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'override'
        && $options->width === 640 && $options->height === 480
        && $options->format === 'png' && $options->socket === null);
    $fake->assertCaptured(fn ($source, $type, $options) => $type === 'url'
        && $options->width === 1280 && $options->height === 720
        && $options->format === 'jpeg' && $options->quality === 85 && $options->socket === 'emails'
        && $options->fullPage && ! $options->javascript && $options->timeout === 60000);
});

test('configuration runs once for each fresh pending screenshot without capturing', function () {
    $fake = new ScreenshotFake;
    $width = 800;
    $calls = 0;
    $fake->configure(function (PendingScreenshot $screenshot) use (&$width, &$calls): void {
        $calls++;
        $screenshot->size($width, 600);
    });

    expect($calls)->toBe(0);
    $first = $fake->html('first');
    $width = 900;
    $second = $fake->html('second');
    $fake->assertNothingCaptured();
    expect($calls)->toBe(2);

    $first->capture();
    $second->capture();
    expect($calls)->toBe(2);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'first' && $options->width === 800);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second' && $options->width === 900);
});

test('configuration can be replaced or cleared without changing existing pending screenshots', function () {
    $fake = new ScreenshotFake;
    $fake->configure(fn (PendingScreenshot $screenshot) => $screenshot->size(800, 600)->jpeg());
    $first = $fake->html('first');
    $fake->configure(fn (PendingScreenshot $screenshot) => $screenshot->size(900, 600));
    $second = $fake->html('second');
    $fake->configure(null);

    $first->capture();
    $second->capture();
    $fake->html('third')->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'first' && $options->width === 800 && $options->format === 'jpeg');
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second' && $options->width === 900 && $options->format === 'png');
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'third' && $options->width === 1024);
});

test('failed configuration does not capture or contaminate subsequent screenshots', function () {
    $fake = new ScreenshotFake;
    $fail = true;
    $fake->configure(function (PendingScreenshot $screenshot) use (&$fail): void {
        if ($fail) {
            $screenshot->jpeg()->size(0, 600);
        }
    });

    expect(fn () => $fake->html('invalid'))->toThrow(InvalidArgumentException::class);
    $fake->assertNothingCaptured();
    $fail = false;
    $fake->html('valid')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options->width === 1024 && $options->format === 'png');
});

test('factories have independent configuration and fakes preserve their baseline options', function () {
    $factory = new Screenshot(options: new CaptureOptions(width: 900));
    $factory->configure(fn (PendingScreenshot $screenshot) => $screenshot->jpeg(85));
    $fake = new ScreenshotFake(factory: $factory);
    $factory->configure(null);
    $fake->html('configured')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options->width === 900 && $options->format === 'jpeg');

    $other = new ScreenshotFake;
    $other->html('default')->capture();
    $other->assertCaptured(fn ($source, $type, $options) => $options->width === 1024 && $options->format === 'png');
});
