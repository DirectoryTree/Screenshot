<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PendingImage;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('pending captures do not start the driver until requested', function () {
    $fake = new ScreenshotFake;
    $pending = $fake->html('<h1>Hello</h1>')->size(800, 600)->jpeg(90)->viewport()
        ->maxSize(1000, 2000)->timeouts(load: 1000, browser: 2000)->javascript()->failOnTimeout();

    $fake->assertNothingCaptured();
    $pending->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === '<h1>Hello</h1>'
        && $type === SourceType::Html && $options->browser->getWidth() === 800 && $options->browser->getHeight() === 600
        && $options->getFormat() === OutputFormat::Jpeg && $options->output->getQuality() === 90 && ! $options->output->getFullPage()
        && $options->output->getMaxWidth() === 1000 && $options->output->getMaxHeight() === 2000
        && $options->browser->getBrowserTimeout() === 2000 && $options->browser->getLoadTimeout() === 1000
        && $options->browser->getJavascript() && $options->browser->getFailOnTimeout());
});

test('omitted timeouts preserve configured values without changing other captures', function ($arguments, $load, $browser) {
    $fake = new ScreenshotFake(browser: BrowserOptions::make()->setTimeouts(load: 20000, browser: 80000));

    $fake->html('override')->timeouts(...$arguments)->capture();
    $fake->html('default')->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'override'
        && $options->browser->getLoadTimeout() === $load && $options->browser->getBrowserTimeout() === $browser);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'default'
        && $options->browser->getLoadTimeout() === 20000 && $options->browser->getBrowserTimeout() === 80000);
})->with([
    'load only' => [['load' => 30000], 30000, 80000],
    'browser only' => [['browser' => 90000], 20000, 90000],
    'neither' => [[], 20000, 80000],
]);

test('successive timeout updates preserve the other current value', function () {
    $fake = new ScreenshotFake;
    $fake->html('hello')->timeouts(load: 30000)->timeouts(browser: 90000)->capture();

    $fake->assertCaptured(fn ($source, $type, $options) => $options->browser->getLoadTimeout() === 30000
        && $options->browser->getBrowserTimeout() === 90000);
});

test('options cannot leak between captures', function () {
    $fake = new ScreenshotFake;
    $fake->html('first')->size(500, 400)->javascript()->capture();
    $fake->html('second')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second'
        && $options->browser->getWidth() === 1024 && ! $options->browser->getJavascript());
    $fake->assertCapturedCount(2);
});

test('directly constructed pending screenshots own their options', function () {
    $fake = new ScreenshotFake;
    $settings = new CaptureOptions(BrowserOptions::make()->setSize(900, 768), ImageOptions::make());
    $first = new PendingImage($fake, 'first', SourceType::Html, $settings);
    $second = new PendingImage($fake, 'second', SourceType::Html, $settings);

    $settings->browser->setSize(1200, $settings->browser->getHeight());
    $settings->output->setFormat(OutputFormat::Jpeg, 50);
    $first->size(500, 400)->capture();
    $second->capture();

    expect($settings->browser->getWidth())->toBe(1200)->and($settings->browser->getHeight())->toBe(768);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'first'
        && $options->browser->getWidth() === 500 && $options->browser->getHeight() === 400);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'second'
        && $options->browser->getWidth() === 900 && $options->browser->getHeight() === 768
        && $options->getFormat() === OutputFormat::Png && $options->output->getQuality() === 80);
});

test('drivers receive independent options snapshots', function () {
    $snapshots = [];
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capture')->twice()->andReturnUsing(function ($source, $type, $options) use (&$snapshots) {
        $snapshots[] = $options;

        return new ImageResult(imageFixture());
    });
    $pending = new PendingImage($driver, 'hello', SourceType::Html, new CaptureOptions(BrowserOptions::make(), ImageOptions::make()));
    $pending->jpeg(95)->capture();
    $pending->size(640, 480);
    expect($snapshots[0]->browser->getWidth())->toBe(1024)->and($snapshots[0]->browser->getHeight())->toBe(768);

    $snapshots[0]->browser->setSize(1, $snapshots[0]->browser->getHeight());
    $snapshots[0]->output->setFormat(OutputFormat::Png, 50);
    $pending->capture();
    expect($snapshots[1]->browser->getWidth())->toBe(640)->and($snapshots[1]->browser->getHeight())->toBe(480)
        ->and($snapshots[1]->getFormat())->toBe(OutputFormat::Jpeg)->and($snapshots[1]->output->getQuality())->toBe(95);
});

test('invalid mutations leave all pending screenshot options unchanged', function ($method, $arguments) {
    $fake = new ScreenshotFake;
    $pending = $fake->html('hello');
    expect(fn () => $pending->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
    $pending->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $options == new CaptureOptions(BrowserOptions::make(), ImageOptions::make()));
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

test('unsafe image options are rejected', function ($method, $arguments) {
    expect(fn () => ImageOptions::make()->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([
    ['setMaxSize', [0, 10000]],
    ['setMaxSize', [10000, 10000]],
    ['setFormat', [OutputFormat::Jpeg, -1]], ['setFormat', [OutputFormat::Jpeg, 101]],
]);

test('socket keys can be overridden and disabled without changing defaults', function () {
    $fake = new ScreenshotFake(browser: BrowserOptions::make()->setSocket('default'));
    $fake->html('named')->socket('old')->socket('emails')->capture();
    $fake->html('dedicated')->withoutSocket()->capture();
    $fake->html('default')->capture();
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'named' && $options->browser->getSocket() === 'emails');
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'dedicated' && $options->browser->getSocket() === null);
    $fake->assertCaptured(fn ($source, $type, $options) => $source === 'default' && $options->browser->getSocket() === 'default');
});

test('empty socket keys fail before a capture starts', function ($key) {
    expect(fn () => BrowserOptions::make()->setSocket($key))->toThrow(InvalidArgumentException::class);
})->with(['', ' ']);

test('image builders reject PDF defaults before calling the driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldNotReceive('capture');

    expect(fn () => new PendingImage($driver, 'invoice', SourceType::Html, new CaptureOptions(BrowserOptions::make(), ImageOptions::make()->setFormat(OutputFormat::Pdf))))
        ->toThrow(InvalidArgumentException::class, 'Image options require PNG or JPEG output.');
});
