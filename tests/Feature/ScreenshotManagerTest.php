<?php

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\ManagesBrowsers;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\ScreenshotManager;
use DirectoryTree\Screenshot\Support\ChromeInstaller;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

test('the built-in Gotenberg driver renders views with configured defaults without resolving Chrome', function () {
    $installer = Mockery::mock(ChromeInstaller::class);
    $installer->shouldNotReceive('path');
    app()->instance(ChromeInstaller::class, $installer);
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], file_get_contents(__DIR__.'/../../resources/blank.pdf')),
        new Response(200, [], base64_decode(imageFixture())),
    ]);
    $stack = HandlerStack::create($mock);

    $stack->push(Middleware::history($history));

    config([
        'screenshot.default' => 'gotenberg',
        'screenshot.gotenberg.url' => 'https://gotenberg.example.test',
        'screenshot.gotenberg.options' => ['handler' => $stack],
        'screenshot.gotenberg.javascript' => false,
        'screenshot.capture.width' => 900,
    ]);

    $manager = app(ScreenshotManager::class);

    expect($manager->getDrivers())->toBe([]);

    Screenshot::view('card', ['name' => '<Steve>'])
        ->pdf()
        ->capture();
    Screenshot::driver('gotenberg')
        ->html('image')
        ->capture();

    expect($manager->driver())->toBe($manager->driver('gotenberg'));
    expect((string) $history[0]['request']->getBody())->toContain('&lt;Steve&gt;');
    expect((string) $history[1]['request']->getBody())->toContain("\r\n900\r\n");
});

test('Chrome is the default driver and is resolved lazily', function () {
    $installer = Mockery::mock(ChromeInstaller::class);
    $installer->shouldReceive('path')->once()->andReturnNull();
    app()->instance(ChromeInstaller::class, $installer);

    $manager = app(ScreenshotManager::class);

    expect($manager->getDrivers())->toBe([]);
    expect($manager->getDefaultDriver())->toBe('chrome');
    expect($manager->driver())->toBe($manager->driver('chrome'));
    expect($manager->getDrivers())->toHaveKey('chrome');
});

test('custom drivers receive the container and are cached until forgotten', function () {
    $created = 0;
    $container = app();

    Screenshot::extend('remote', function ($app) use ($container, &$created) {
        expect($app)->toBe($container);
        $created++;

        return new ScreenshotFake;
    });

    expect($created)->toBe(0);

    $factory = Screenshot::driver('remote');

    expect(Screenshot::driver('remote'))->toBe($factory);
    expect($created)->toBe(1);

    app(ScreenshotManager::class)->forgetDrivers();

    expect(Screenshot::driver('remote'))->not->toBe($factory);
    expect($created)->toBe(2);
});

test('default and explicitly selected drivers share the fluent API with independent captures', function () {
    config(['screenshot.default' => 'remote', 'screenshot.capture.width' => 900]);
    $installer = Mockery::mock(ChromeInstaller::class);
    $installer->shouldNotReceive('path');
    app()->instance(ChromeInstaller::class, $installer);
    $remote = new ScreenshotFake;
    $other = new ScreenshotFake;

    Screenshot::extend('remote', fn () => $remote);
    Screenshot::extend('other', fn () => $other);

    Screenshot::html('first')->size(400, 300)->jpeg()->capture();
    Screenshot::driver('other')->url('https://example.com/invoice')->element('#invoice')->pdf()->capture();
    Screenshot::view('card', ['name' => '<Steve>'])->capture();

    $remote->assertCapturedCount(2);
    $remote->assertCaptured(fn ($source, $type, $options) => $source === 'first'
        && $options->format() === OutputFormat::Jpeg
        && $options->browser()->getWidth() === 400);
    $remote->assertCaptured(fn ($source, $type, $options) => str_contains($source, '&lt;Steve&gt;')
        && $type === SourceType::Html
        && $options->format() === OutputFormat::Png
        && $options->browser()->getWidth() === 900);
    $other->assertCapturedCount(1);
    $other->assertCaptured(fn ($source, $type, $options) => $source === 'https://example.com/invoice'
        && $type === SourceType::Url
        && $options->format() === OutputFormat::Pdf
        && $options->element() === '#invoice'
        && $options->browser()->getWidth() === 900);
});

test('custom creators may replace the built-in Chrome driver', function () {
    $driver = new ScreenshotFake;
    Screenshot::extend('chrome', fn () => $driver);

    Screenshot::html('custom chrome')->capture();

    $driver->assertCapturedCount(1);
});

test('unsupported driver names fail on resolution', function () {
    expect(fn () => Screenshot::driver('missing'))
        ->toThrow(InvalidArgumentException::class, 'Driver [missing] not supported.');
});

test('browser shutdowns are forwarded to the selected driver', function () {
    $driver = Mockery::mock(Driver::class, ManagesBrowsers::class);
    $driver->shouldReceive('close')->once()->with('invoices');
    Screenshot::extend('remote', fn () => $driver);

    Screenshot::driver('remote')->close('invoices');
});

test('the facade recognizes its recording fake', function () {
    expect(Screenshot::isFake())->toBeFalse();

    Screenshot::fake();

    expect(Screenshot::isFake())->toBeTrue();

    Screenshot::fake();

    expect(Screenshot::isFake())->toBeTrue();
});

test('the facade fake records captures and shutdowns across every named driver', function () {
    config(['screenshot.default' => 'remote', 'screenshot.capture.width' => 900]);
    Screenshot::extend('remote', fn () => throw new RuntimeException('Real driver resolved.'));
    Screenshot::driver('chrome');
    $result = new ImageResult(imageFixture());
    $fake = Screenshot::fake($result);

    expect(app(ScreenshotManager::class))->toBe($fake);
    expect(Screenshot::html('default')->capture())->toBe($result);
    expect(Screenshot::driver('chrome')->html('chrome')->capture())->toBe($result);
    app(ScreenshotManager::class)->driver('other')->view('card', ['name' => 'Steve'])->pdf()->capture();
    Screenshot::driver('remote')->close('invoices');

    $fake->assertCapturedCount(3);
    Screenshot::assertCaptured(fn ($source, $type, $options) => $options->format() === OutputFormat::Pdf
        && $options->browser()->getWidth() === 900);
    Screenshot::assertClosed('invoices');

    Screenshot::fake();

    Screenshot::assertNothingCaptured();
    Screenshot::assertNothingClosed();
    Screenshot::driver('chrome')->html('fresh')->capture();
    Screenshot::assertCapturedCount(1);
    $fake->assertCapturedCount(3);
});
