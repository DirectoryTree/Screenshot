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
        && $options->getWidth() === 640 && $options->getHeight() === 480
        && $options->getFormat() === 'png' && $options->getSocket() === null);
    $fake->assertCaptured(fn ($source, $type, $options) => $type === 'url'
        && $options->getWidth() === 1280 && $options->getHeight() === 720
        && $options->getFormat() === 'jpeg' && $options->getQuality() === 85 && $options->getSocket() === 'emails'
        && $options->getFullPage() && ! $options->getJavascript() && $options->getBrowserTimeout() === 60000);
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
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'first' && $options->getWidth() === 800);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second' && $options->getWidth() === 900);
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

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'first' && $options->getWidth() === 800 && $options->getFormat() === 'jpeg');
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second' && $options->getWidth() === 900 && $options->getFormat() === 'png');
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'third' && $options->getWidth() === 1024);
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
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getWidth() === 1024 && $options->getFormat() === 'png');
});

test('factories have independent configuration and fakes preserve their baseline options', function () {
    $options = new CaptureOptions(width: 900);
    $factory = new Screenshot(options: $options);
    $factory->configure(fn (PendingScreenshot $screenshot) => $screenshot->jpeg(85));
    $fake = ScreenshotFake::from($factory);
    $options->setSize(800, $options->getHeight());
    $factory->configure(null);
    $fake->html('configured')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options->getWidth() === 900 && $options->getFormat() === 'jpeg');

    $other = new ScreenshotFake;
    $other->html('default')->capture();
    $other->assertCaptured(fn ($source, $type, $options) => $options->getWidth() === 1024 && $options->getFormat() === 'png');
});
