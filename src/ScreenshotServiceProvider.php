<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use Illuminate\Support\ServiceProvider;

class ScreenshotServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/screenshot.php', 'screenshot');

        $this->app->singleton(Driver::class, function ($app) {
            return new ChromeDriver(
                $app['config']->get('screenshot.binary'),
                $app['config']->get('screenshot.browser_options', []),
                $app['config']->get('screenshot.socket_directory'),
                $app['config']->get('screenshot.socket_lock_timeout', 60000),
            );
        });

        $this->app->singleton(Screenshot::class, function ($app) {
            return new Screenshot(
                $app->make(Driver::class),
                new CaptureOptions(...$app['config']->get('screenshot.defaults', [])),
                $app['view'],
            );
        });
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/screenshot.php' => config_path('screenshot.php'),
            ], 'screenshot-config');
        }
    }
}
