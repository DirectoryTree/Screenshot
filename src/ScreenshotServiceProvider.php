<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Commands\InstallCommand;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
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
            $app['config']->get('screenshot.browser.directory'),
        ));

        $this->app->singleton(Driver::class, function ($app) {
            return new ChromeDriver(
                $app['config']->get('screenshot.browser.binary') ?? $app->make(ChromeInstaller::class)->path(),
                $app['config']->get('screenshot.browser.options', []),
                $app['config']->get('screenshot.sockets.directory'),
                $app['config']->get('screenshot.sockets.lock_timeout', 60000),
            );
        });

        $this->app->singleton(Screenshot::class, function ($app) {
            $capture = $app['config']->get('screenshot.capture');
            $image = $app['config']->get('screenshot.image');
            $pdf = $app['config']->get('screenshot.pdf');
            [$width, $height] = PaperSize::from($pdf['paper'])->dimensions();

            return new Screenshot(
                driver: $app->make(Driver::class),
                browser: (new BrowserOptions)
                    ->setSize($capture['width'], $capture['height'])
                    ->setTimeouts($capture['load_timeout'], $capture['browser_timeout'])
                    ->setJavascript($capture['javascript'])
                    ->setFailOnTimeout($capture['fail_on_timeout'])
                    ->setSocket($capture['socket']),
                image: (new ImageOptions)
                    ->setFormat(OutputFormat::from($image['format']), $image['quality'])
                    ->setMaxSize($image['max_width'], $image['max_height'])
                    ->setFullPage($image['full_page']),
                pdf: (new PdfOptions)
                    ->setLayout(
                        width: $width,
                        height: $height,
                        landscape: $pdf['landscape'],
                        top: $pdf['margins']['top'],
                        right: $pdf['margins']['right'],
                        bottom: $pdf['margins']['bottom'],
                        left: $pdf['margins']['left'],
                    )
                    ->setBackground($pdf['background'])
                    ->setPreferCssPageSize($pdf['prefer_css_page_size']),
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
