<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Commands\InstallCommand;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Support\ChromeInstaller;
use Illuminate\Support\ServiceProvider;

class ScreenshotServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/screenshot.php', 'screenshot');

        $this->app->singleton(ChromeInstaller::class, fn ($app) => new ChromeInstaller(
            $app['config']->get('screenshot.browser_directory'),
        ));

        $this->app->singleton(Driver::class, function ($app) {
            return new ChromeDriver(
                $app['config']->get('screenshot.binary') ?? $app->make(ChromeInstaller::class)->path(),
                $app['config']->get('screenshot.browser_options', []),
                $app['config']->get('screenshot.sockets.directory'),
                $app['config']->get('screenshot.sockets.lock_timeout', 60000),
            );
        });

        $this->app->singleton(Screenshot::class, function ($app) {
            return new Screenshot(
                driver: $app->make(Driver::class),
                views: $app['view'],
            );
        });
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__.'/../config/screenshot.php' => config_path('screenshot.php'),
            ], 'screenshot-config');
        }
    }
}
