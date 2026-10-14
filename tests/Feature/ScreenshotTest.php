<?php

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\Screenshot as Factory;
use DirectoryTree\Screenshot\ScreenshotResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

test('the provider resolves configured defaults and a replaceable driver', function () {
    config(['screenshot.defaults.width' => 900]);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capture')->once()->withArgs(fn ($source, $type, $options) => $source === 'hello' && $options->width === 900)
        ->andReturn(new ScreenshotResult(imageFixture()));
    $this->app->instance(Driver::class, $driver);

    expect(Screenshot::html('hello')->capture()->width())->toBe(1);
});

test('the facade fake replaces the container factory and renders escaped Blade views', function () {
    Screenshot::fake();
    Screenshot::assertNothingCaptured();
    $this->app->make(Factory::class)->view('card', ['name' => '<Steve>'])->capture();
    Screenshot::assertCaptured(fn ($source, $type) => str_contains($source, '&lt;Steve&gt;') && $type === 'html');
    Screenshot::assertCapturedCount(1);
});

test('captures can be stored on named Laravel disks', function () {
    Storage::fake('screenshots');
    Screenshot::fake();
    $result = Screenshot::html('hello')->store('preview.png', 'screenshots');

    Storage::disk('screenshots')->assertExists('preview.png');
    expect(Storage::disk('screenshots')->get('preview.png'))->toBe($result->contents());
});

test('failed disk writes throw an exception', function () {
    Storage::shouldReceive('disk')->with(null)->once()->andReturnSelf();
    Storage::shouldReceive('put')->once()->andReturn(false);

    expect(fn () => (new ScreenshotResult(imageFixture()))->store('preview.png'))
        ->toThrow(RuntimeException::class, 'Unable to store');
});

test('configuration has a publishable tag', function () {
    $paths = ServiceProvider::pathsToPublish(null, 'screenshot-config');

    expect(array_values($paths))->toContain(config_path('screenshot.php'));
});
