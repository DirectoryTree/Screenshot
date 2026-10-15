<?php

use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\Support\ChromeInstaller;

use function Pest\Laravel\artisan;

test('the install command downloads stable Chrome by default', function () {
    $installer = Mockery::mock(ChromeInstaller::class);

    $installer->shouldReceive('path')
        ->once()
        ->andReturnNull();

    $installer->shouldReceive('install')
        ->once()
        ->with(null, false)
        ->andReturn('/browsers/chrome');

    app()->instance(ChromeInstaller::class, $installer);

    artisan('screenshot:install')->expectsOutput('/browsers/chrome')->assertSuccessful();
});

test('the install command accepts a version and explains explicit binary precedence', function () {
    config(['screenshot.browser.binary' => '/system/chrome']);
    $installer = Mockery::mock(ChromeInstaller::class);

    $installer->shouldReceive('path')
        ->once()
        ->andReturnNull();

    $installer->shouldReceive('install')
        ->once()
        ->with('155.0.8059.79', false)
        ->andReturn('/browsers/chrome');

    app()->instance(ChromeInstaller::class, $installer);

    artisan('screenshot:install', ['--browser-version' => '155.0.8059.79'])
        ->expectsOutputToContain('takes precedence')->assertSuccessful();
});

test('the install command reports installation failures', function () {
    $installer = Mockery::mock(ChromeInstaller::class);

    $installer->shouldReceive('path')
        ->once()
        ->andReturnNull();

    $installer->shouldReceive('install')
        ->once()
        ->andThrow(new RuntimeException('Download failed.'));

    app()->instance(ChromeInstaller::class, $installer);

    artisan('screenshot:install')->expectsOutputToContain('Download failed.')->assertFailed();
});

test('the install command reports an existing browser and preserves the binary warning', function () {
    config(['screenshot.browser.binary' => '/system/chrome']);
    $installer = Mockery::mock(ChromeInstaller::class);

    $installer->shouldReceive('path')
        ->once()
        ->andReturn('/browsers/chrome');

    $installer->shouldReceive('install')
        ->once()
        ->with(null, false)
        ->andReturn('/browsers/chrome');

    app()->instance(ChromeInstaller::class, $installer);

    artisan('screenshot:install')->expectsOutputToContain('Chrome is already installed.')
        ->expectsOutput('/browsers/chrome')
        ->expectsOutputToContain('takes precedence')
        ->doesntExpectOutputToContain('Restart long-running queue workers')
        ->assertSuccessful();
});

test('the install command explicitly requests an update', function () {
    $installer = Mockery::mock(ChromeInstaller::class);

    $installer->shouldReceive('path')
        ->once()
        ->andReturn('/browsers/old/chrome');

    $installer->shouldReceive('install')
        ->once()
        ->with(null, true)
        ->andReturn('/browsers/new/chrome');

    app()->instance(ChromeInstaller::class, $installer);

    artisan('screenshot:install', ['--update' => true])->expectsOutput('/browsers/new/chrome')
        ->expectsOutputToContain('Restart long-running queue workers')
        ->assertSuccessful();
});

test('the install command rejects conflicting version options', function () {
    artisan('screenshot:install', ['--update' => true, '--browser-version' => '155.0.8059.79'])
        ->expectsOutputToContain('Use either --update or --browser-version, not both.')
        ->assertFailed();
});

test('the driver uses installed Chrome unless the application explicitly configured a binary', function ($configured, $expected) {
    config(['screenshot.browser.binary' => $configured]);
    $installer = Mockery::mock(ChromeInstaller::class);

    $installer->shouldReceive('path')
        ->times($configured === null ? 1 : 0)
        ->andReturn('/installed/chrome');

    app()->instance(ChromeInstaller::class, $installer);
    $factory = Screenshot::driver('chrome');
    $driver = (new ReflectionProperty($factory, 'driver'))->getValue($factory);

    expect((new ReflectionProperty($driver, 'binary'))->getValue($driver))->toBe($expected);
})->with([
    [null, '/installed/chrome'],
    ['/system/chrome', '/system/chrome'],
]);
