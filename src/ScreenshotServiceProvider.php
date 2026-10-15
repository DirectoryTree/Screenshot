<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Commands\InstallCommand;
use DirectoryTree\Screenshot\Contracts\Factory;
use DirectoryTree\Screenshot\Support\ChromeInstaller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ScreenshotServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/screenshot.php', 'screenshot');

        $this->app->singleton(ChromeInstaller::class, fn (Application $app): ChromeInstaller => new ChromeInstaller(
            $app['config']->get('screenshot.browser.directory'),
        ));

        $this->app->singleton(ScreenshotManager::class);
        $this->app->alias(ScreenshotManager::class, Factory::class);
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
