<?php

use DirectoryTree\Screenshot\Contracts\Factory;
use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\ScreenshotManager;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;

test('the factory contract injects the configured manager into application services', function () {
    config(['screenshot.default' => 'remote']);
    $driver = new ScreenshotFake;
    Screenshot::extend('remote', fn () => $driver);

    expect(app(Factory::class))->toBe(app(ScreenshotManager::class));

    app(ScreenshotFactoryConsumer::class)->capture();

    $driver->assertCaptured(fn ($source) => $source === 'invoice');
    $driver->assertCapturedCount(1);
});

test('faking replaces a previously resolved factory contract for constructor injection', function ($driver) {
    config(['screenshot.default' => 'remote']);
    Screenshot::extend('remote', fn () => throw new RuntimeException('Real driver resolved.'));
    $original = app(Factory::class);
    $result = new ImageResult(imageFixture());
    $fake = Screenshot::fake($result);

    expect(app(Factory::class))->toBe($fake)->not->toBe($original);
    expect(app(ScreenshotFactoryConsumer::class)->capture($driver))->toBe($result);
    Screenshot::assertCapturedCount(1);

    $nextResult = new ImageResult(imageFixture());
    $nextFake = Screenshot::fake($nextResult);

    expect(app(Factory::class))->toBe($nextFake)->not->toBe($fake);
    Screenshot::assertNothingCaptured();
    expect(app(ScreenshotFactoryConsumer::class)->capture($driver))->toBe($nextResult);
    Screenshot::assertCapturedCount(1);
    $fake->assertCapturedCount(1);
})->with(['default driver' => null, 'named driver' => 'remote']);

class ScreenshotFactoryConsumer
{
    public function __construct(
        protected Factory $screenshots,
    ) {}

    public function capture(?string $driver = null): ImageResult
    {
        return $this->screenshots->driver($driver)->html('invoice')->capture();
    }
}
