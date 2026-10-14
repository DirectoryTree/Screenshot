<?php

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\PendingScreenshot;
use DirectoryTree\Screenshot\Screenshot as Factory;
use DirectoryTree\Screenshot\ScreenshotResult;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

test('a service provider configures defaults with a replaceable driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capture')->once()->withArgs(fn ($source, $type, $options) => $source === 'hello' && $options->width === 900)
        ->andReturn(new ScreenshotResult(imageFixture()));
    $this->app->instance(Driver::class, $driver);

    $this->app->register(new class($this->app) extends ServiceProvider
    {
        public function boot(): void
        {
            Screenshot::configure(fn (PendingScreenshot $screenshot) => $screenshot->size(900, 600));
        }
    });

    expect(Screenshot::html('hello')->capture()->width())->toBe(1);
});

test('the facade fake replaces the container factory and renders escaped Blade views', function () {
    $calls = 0;
    Screenshot::configure(function (PendingScreenshot $screenshot) use (&$calls) {
        $calls++;
        $screenshot->jpeg(85);
    });
    Screenshot::fake();
    expect($calls)->toBe(0);
    Screenshot::assertNothingCaptured();
    $this->app->make(Factory::class)->view('card', ['name' => '<Steve>'])->capture();
    Screenshot::assertCaptured(fn ($source, $type, $options) => str_contains($source, '&lt;Steve&gt;')
        && $type === 'html' && $options->format === 'jpeg' && $options->quality === 85);
    Screenshot::assertCapturedCount(1);
    expect($calls)->toBe(1);
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

test('the facade fake preserves configured socket defaults without starting a browser', function () {
    Screenshot::configure(fn (PendingScreenshot $screenshot) => $screenshot->socket('emails'));
    Screenshot::fake();
    Screenshot::html('shared')->capture();
    Screenshot::html('dedicated')->withoutSocket()->capture();
    Screenshot::assertNothingClosed();
    Screenshot::close('emails');
    Screenshot::assertClosed(key: 'emails');
    Screenshot::assertNotClosed(key: 'invoices');

    Screenshot::assertCaptured(fn ($source, $type, $options) => $source === 'shared' && $options->socket === 'emails');
    Screenshot::assertCaptured(fn ($source, $type, $options) => $source === 'dedicated' && $options->socket === null);
    Screenshot::assertCapturedCount(2);

    Screenshot::fake();
    Screenshot::assertNothingClosed();
    Screenshot::assertNothingCaptured();
    Screenshot::url('https://example.com')->capture();
    Screenshot::assertCaptured(fn ($source, $type, $options) => $type === 'url' && $options->socket === 'emails');
});

test('the configured socket directory is used for lifecycle operations', function () {
    $directory = sys_get_temp_dir().'/screenshot-config-'.bin2hex(random_bytes(8));
    config(['screenshot.socket_directory' => $directory]);

    try {
        Screenshot::close('emails');
        $path = BrowserSocket::named('emails', $directory)->path();
        expect(file_exists($path.'.lock'))->toBeTrue();
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});
