<?php

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\OutputFormat;
use DirectoryTree\Screenshot\PendingImage;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\SourceType;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('pending captures do not start the driver until requested', function () {
    $fake = new ScreenshotFake;
    $pending = $fake->html('<h1>Hello</h1>')->size(800, 600)->jpeg(90)->viewport()
        ->maxSize(1000, 2000)->timeouts(load: 1000, browser: 2000)->javascript()->failOnTimeout();

    $fake->assertNothingCaptured();
    $pending->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === '<h1>Hello</h1>'
        && $type === SourceType::Html && $options->getWidth() === 800 && $options->getHeight() === 600
        && $options->getFormat() === OutputFormat::Jpeg && $options->getQuality() === 90 && ! $options->getFullPage()
        && $options->getMaxWidth() === 1000 && $options->getMaxHeight() === 2000
        && $options->getBrowserTimeout() === 2000 && $options->getLoadTimeout() === 1000
        && $options->getJavascript() && $options->getFailOnTimeout());
});

test('omitted timeouts preserve configured values without changing other captures', function ($arguments, $load, $browser) {
    $fake = new ScreenshotFake;
    $fake->configure(OutputFormat::Png, fn (PendingImage $screenshot) => $screenshot->timeouts(load: 20000, browser: 80000));

    $fake->html('override')->timeouts(...$arguments)->capture();
    $fake->html('default')->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'override'
        && $options->getLoadTimeout() === $load && $options->getBrowserTimeout() === $browser);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'default'
        && $options->getLoadTimeout() === 20000 && $options->getBrowserTimeout() === 80000);
})->with([
    'load only' => [['load' => 30000], 30000, 80000],
    'browser only' => [['browser' => 90000], 20000, 90000],
    'neither' => [[], 20000, 80000],
]);

test('successive timeout updates preserve the other current value', function () {
    $fake = new ScreenshotFake;
    $fake->html('hello')->timeouts(load: 30000)->timeouts(browser: 90000)->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $options->getLoadTimeout() === 30000
        && $options->getBrowserTimeout() === 90000);
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
    $options = new ImageOptions(width: 900);
    $first = new PendingImage($fake, 'first', SourceType::Html, $options);
    $second = new PendingImage($fake, 'second', SourceType::Html, $options);

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

        return new ImageResult(imageFixture());
    });
    $pending = new PendingImage($driver, 'hello', SourceType::Html, new ImageOptions);
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
    $fake->assertCaptured(fn ($source, $type, $options) => $options == new ImageOptions);
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
    'zero browser timeout' => ['timeouts', ['browser' => 0]],
    'negative browser timeout' => ['timeouts', ['browser' => -1]],
    'zero load timeout' => ['timeouts', ['load' => 0]],
    'negative load timeout' => ['timeouts', ['load' => -1]],
    'invalid browser timeout with valid load timeout' => ['timeouts', ['load' => 30000, 'browser' => 0]],
    'invalid load timeout with valid browser timeout' => ['timeouts', ['load' => 0, 'browser' => 90000]],
    'empty socket key' => ['socket', [' ']],
]);

test('unsupported URLs are rejected', function ($url) {
    expect(fn () => (new Screenshot)->url($url))->toThrow(InvalidArgumentException::class);
})->with(['file:///etc/hosts', 'javascript:alert(1)', 'not a url', 'ftp://example.com/file']);

test('HTTP and HTTPS URLs are accepted', function ($url) {
    $fake = new ScreenshotFake;
    $fake->url($url)->capture();
    $fake->assertCaptured(fn ($source, $type) => $source === $url && $type === SourceType::Url);
})->with(['https://example.com', 'http://127.0.0.1:8000']);

test('standalone views require a view factory', function () {
    expect(fn () => (new Screenshot)->view('card'))->toThrow(LogicException::class);
});

test('unsafe capture options are rejected', function ($values) {
    expect(fn () => new ImageOptions(...$values))->toThrow(InvalidArgumentException::class);
})->with([
    [['width' => 0]], [['height' => -1]], [['width' => 16385]],
    [['width' => 10000, 'height' => 10000]], [['maxWidth' => 0]],
    [['maxWidth' => 10000, 'maxHeight' => 10000]],
    [['quality' => -1]], [['quality' => 101]], [['browserTimeout' => 0]], [['loadTimeout' => 0]],
]);

test('socket keys can be overridden and disabled without changing defaults', function () {
    $fake = new ScreenshotFake(options: new ImageOptions(socket: 'default'));
    $fake->html('named')->socket('old')->socket('emails')->capture();
    $fake->html('dedicated')->withoutSocket()->capture();
    $fake->html('default')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'named' && $options->getSocket() === 'emails');
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'dedicated' && $options->getSocket() === null);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'default' && $options->getSocket() === 'default');
});

test('empty socket keys fail before a capture starts', function ($key) {
    expect(fn () => new ImageOptions(socket: $key))->toThrow(InvalidArgumentException::class);
})->with(['', ' ']);

test('image builders reject PDF defaults before calling the driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldNotReceive('capture');

    expect(fn () => new PendingImage($driver, 'invoice', SourceType::Html, new ImageOptions(format: OutputFormat::Pdf)))
        ->toThrow(InvalidArgumentException::class, 'Image options require PNG or JPEG output.');
});
