<?php

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\PendingScreenshot;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\ScreenshotResult;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('pending captures do not start the driver until requested', function () {
    $fake = new ScreenshotFake;
    $pending = $fake->html('<h1>Hello</h1>')->size(800, 600)->jpeg(90)->viewport()
        ->maxSize(1000, 2000)->timeout(2000)->loadTimeout(1000)->javascript()->failOnTimeout();

    $fake->assertNothingCaptured();
    $pending->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === '<h1>Hello</h1>'
        && $type === 'html' && $options->getWidth() === 800 && $options->getHeight() === 600
        && $options->getFormat() === 'jpeg' && $options->getQuality() === 90 && ! $options->getFullPage()
        && $options->getMaxWidth() === 1000 && $options->getMaxHeight() === 2000
        && $options->getTimeout() === 2000 && $options->getLoadTimeout() === 1000
        && $options->getJavascript() && $options->getFailOnTimeout());
});

test('options cannot leak between captures', function () {
    $fake = new ScreenshotFake;
    $fake->html('first')->size(500, 400)->javascript()->capture();
    $fake->html('second')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second'
        && $options->getWidth() === 1024 && ! $options->getJavascript());
    $fake->assertCapturedCount(2);
});

test('directly constructed pending screenshots own their options', function () {
    $fake = new ScreenshotFake;
    $options = new CaptureOptions(width: 900);
    $first = new PendingScreenshot($fake, 'first', 'html', $options);
    $second = new PendingScreenshot($fake, 'second', 'html', $options);

    $options->setSize(1200, $options->getHeight());
    $first->size(500, 400)->capture();
    $second->capture();

    expect($options->getWidth())->toBe(1200)->and($options->getHeight())->toBe(768);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'first'
        && $options->getWidth() === 500 && $options->getHeight() === 400);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second'
        && $options->getWidth() === 900 && $options->getHeight() === 768);
});

test('drivers receive independent options snapshots', function () {
    $snapshots = [];
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capture')->twice()->andReturnUsing(function ($source, $type, $options) use (&$snapshots) {
        $snapshots[] = $options;

        return new ScreenshotResult(imageFixture());
    });
    $pending = new PendingScreenshot($driver, 'hello', 'html', new CaptureOptions);
    $pending->capture();
    $pending->size(640, 480);
    expect($snapshots[0]->getWidth())->toBe(1024)->and($snapshots[0]->getHeight())->toBe(768);

    $snapshots[0]->setSize(1, $snapshots[0]->getHeight());
    $pending->capture();
    expect($snapshots[1]->getWidth())->toBe(640)->and($snapshots[1]->getHeight())->toBe(480);
});

test('invalid mutations leave all pending screenshot options unchanged', function ($method, $arguments) {
    $fake = new ScreenshotFake;
    $pending = $fake->html('hello');
    expect(fn () => $pending->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
    $pending->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options == new CaptureOptions);
})->with([
    'zero width' => ['size', [0, 768]],
    'invalid height' => ['size', [500, -1]],
    'oversized width' => ['size', [16385, 1]],
    'oversized height' => ['size', [1, 16385]],
    'oversized viewport area' => ['size', [10000, 10000]],
    'invalid maximum height' => ['maxSize', [500, 0]],
    'oversized maximum area' => ['maxSize', [10000, 10000]],
    'negative quality' => ['jpeg', [-1]],
    'excessive quality' => ['jpeg', [101]],
    'zero timeout' => ['timeout', [0]],
    'negative load timeout' => ['loadTimeout', [-1]],
    'empty socket key' => ['socket', [' ']],
]);

test('unsupported URLs are rejected', function ($url) {
    expect(fn () => (new Screenshot)->url($url))->toThrow(InvalidArgumentException::class);
})->with(['file:///etc/hosts', 'javascript:alert(1)', 'not a url', 'ftp://example.com/file']);

test('HTTP and HTTPS URLs are accepted', function ($url) {
    $fake = new ScreenshotFake;
    $fake->url($url)->capture();
    $fake->assertCaptured(fn ($source, $type) => $source === $url && $type === 'url');
})->with(['https://example.com', 'http://127.0.0.1:8000']);

test('standalone views require a view factory', function () {
    expect(fn () => (new Screenshot)->view('card'))->toThrow(LogicException::class);
});

test('unsafe capture options are rejected', function ($values) {
    expect(fn () => new CaptureOptions(...$values))->toThrow(InvalidArgumentException::class);
})->with([
    [['width' => 0]], [['height' => -1]], [['width' => 16385]],
    [['width' => 10000, 'height' => 10000]], [['maxWidth' => 0]],
    [['maxWidth' => 10000, 'maxHeight' => 10000]], [['format' => 'gif']],
    [['quality' => -1]], [['quality' => 101]], [['timeout' => 0]], [['loadTimeout' => 0]],
]);

test('socket keys can be overridden and disabled without changing defaults', function () {
    $fake = new ScreenshotFake(options: new CaptureOptions(socket: 'default'));
    $fake->html('named')->socket('old')->socket('emails')->capture();
    $fake->html('dedicated')->withoutSocket()->capture();
    $fake->html('default')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'named' && $options->getSocket() === 'emails');
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'dedicated' && $options->getSocket() === null);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'default' && $options->getSocket() === 'default');
});

test('empty socket keys fail before a capture starts', function ($key) {
    expect(fn () => new CaptureOptions(socket: $key))->toThrow(InvalidArgumentException::class);
})->with(['', ' ']);
