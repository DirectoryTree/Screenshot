<?php

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('pending captures do not start the driver until requested', function () {
    $fake = new ScreenshotFake;
    $pending = $fake->html('<h1>Hello</h1>')->size(800, 600)->jpeg(90)->viewport()
        ->maxSize(1000, 2000)->timeout(2000)->loadTimeout(1000)->javascript()->failOnTimeout();

    $fake->assertNothingCaptured();
    $pending->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === '<h1>Hello</h1>'
        && $type === 'html' && $options->width === 800 && $options->height === 600
        && $options->format === 'jpeg' && $options->quality === 90 && ! $options->fullPage
        && $options->maxWidth === 1000 && $options->maxHeight === 2000
        && $options->timeout === 2000 && $options->loadTimeout === 1000
        && $options->javascript && $options->failOnTimeout);
});

test('options cannot leak between captures', function () {
    $fake = new ScreenshotFake;
    $fake->html('first')->size(500, 400)->javascript()->capture();
    $fake->html('second')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second'
        && $options->width === 1024 && ! $options->javascript);
    $fake->assertCapturedCount(2);
});

test('invalid mutations leave the pending screenshot usable', function () {
    $fake = new ScreenshotFake;
    $pending = $fake->html('hello');
    expect(fn () => $pending->size(0, 768))->toThrow(InvalidArgumentException::class);
    $pending->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options->width === 1024);
});

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

test('socket selectors override one another and can be disabled', function () {
    $fake = new ScreenshotFake;
    $fake->html('named')->socketPath('/tmp/old.socket')->socket('emails')->capture();
    $fake->html('path')->socket('old')->socketPath('/tmp/browser.socket')->capture();
    $fake->html('dedicated')->socket('old')->withoutSocket()->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'named' && $options->socket === 'emails' && $options->socketPath === null);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'path' && $options->socket === null && $options->socketPath === '/tmp/browser.socket');
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'dedicated' && $options->socket === null && $options->socketPath === null);
});

test('invalid socket selectors fail before a capture starts', function ($options) {
    expect(fn () => new CaptureOptions(...$options))->toThrow(InvalidArgumentException::class);
})->with([
    [['socket' => ' ']], [['socketPath' => '']], [['socketPath' => "bad\0path"]],
    [['socketPath' => 'https://example.com/socket']], [['socket' => 'key', 'socketPath' => '/tmp/socket']],
]);
