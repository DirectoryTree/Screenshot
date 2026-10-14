<?php

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Support\ChromeInstaller;

use function Pest\Laravel\artisan;

test('the install command downloads stable Chrome by default', function () {
    $installer = Mockery::mock(ChromeInstaller::class);
    $installer->shouldReceive('install')->once()->with(null)->andReturn('/browsers/chrome');
    app()->instance(ChromeInstaller::class, $installer);

    artisan('screenshot:install')->expectsOutput('/browsers/chrome')->assertSuccessful();
});

test('the install command accepts a version and explains explicit binary precedence', function () {
    config(['screenshot.browser.binary' => '/system/chrome']);
    $installer = Mockery::mock(ChromeInstaller::class);
    $installer->shouldReceive('install')->once()->with('155.0.8059.79')->andReturn('/browsers/chrome');
    app()->instance(ChromeInstaller::class, $installer);

    artisan('screenshot:install', ['--browser-version' => '155.0.8059.79'])
        ->expectsOutputToContain('takes precedence')->assertSuccessful();
});

test('the install command reports installation failures', function () {
    $installer = Mockery::mock(ChromeInstaller::class);
    $installer->shouldReceive('install')->once()->andThrow(new RuntimeException('Download failed.'));
    app()->instance(ChromeInstaller::class, $installer);

    artisan('screenshot:install')->expectsOutputToContain('Download failed.')->assertFailed();
});

test('the driver uses installed Chrome unless the application explicitly configured a binary', function ($configured, $expected) {
    config(['screenshot.browser.binary' => $configured]);
    $installer = Mockery::mock(ChromeInstaller::class);
    $installer->shouldReceive('path')->times($configured === null ? 1 : 0)->andReturn('/installed/chrome');
    app()->instance(ChromeInstaller::class, $installer);
    $driver = app()->make(Driver::class);

    expect((new ReflectionProperty($driver, 'binary'))->getValue($driver))->toBe($expected);
})->with([
    [null, '/installed/chrome'],
    ['/system/chrome', '/system/chrome'],
]);
