<?php

namespace DirectoryTree\Screenshot;

use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Contracts\Factory;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\Support\ChromeInstaller;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use DirectoryTree\Screenshot\Testing\ScreenshotManagerFake;
use Illuminate\Support\Manager;

/**
 * @mixin Screenshot
 */
class ScreenshotManager extends Manager implements Factory
{
    /**
     * Create a recording manager using the configured capture defaults.
     */
    public function fake(ImageResult|PdfResult|null $result = null): ScreenshotManagerFake
    {
        return new ScreenshotManagerFake(ScreenshotFake::from(
            $this->createScreenshot(new ScreenshotFake),
            $result,
        ));
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('screenshot.default');
    }

    /**
     * Get a screenshot factory for the given driver.
     */
    public function driver($driver = null): Screenshot
    {
        return parent::driver($driver);
    }

    /**
     * Wrap the resolved capture driver in a configured screenshot factory.
     */
    protected function createDriver($driver): Screenshot
    {
        return $this->createScreenshot(parent::createDriver($driver));
    }

    /**
     * Create the Chrome capture driver.
     */
    protected function createChromeDriver(): ChromeDriver
    {
        return new ChromeDriver(
            $this->config->get('screenshot.browser.binary') ?? $this->container->make(ChromeInstaller::class)->path(),
            $this->config->get('screenshot.browser.options', []),
            $this->config->get('screenshot.sockets.directory'),
            $this->config->get('screenshot.sockets.lock_timeout', 60000),
        );
    }

    /**
     * Create a screenshot factory with the application's capture defaults.
     */
    protected function createScreenshot(Driver $driver): Screenshot
    {
        $capture = $this->config->get('screenshot.capture');
        $image = $this->config->get('screenshot.image');
        $pdf = $this->config->get('screenshot.pdf');

        [$width, $height] = PaperSize::from($pdf['paper'])->dimensions();

        return new Screenshot(
            driver: $driver,
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
            views: $this->container->make('view'),
        );
    }
}
