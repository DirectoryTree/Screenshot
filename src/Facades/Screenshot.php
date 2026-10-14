<?php

namespace DirectoryTree\Screenshot\Facades;

use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\PendingScreenshot;
use DirectoryTree\Screenshot\Screenshot as Factory;
use DirectoryTree\Screenshot\ScreenshotResult;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Factory configure(?callable $callback)
 * @method static PendingScreenshot html(string $html)
 * @method static PendingScreenshot url(string $url)
 * @method static PendingScreenshot view(string $view, array $data = [])
 * @method static void close(string $key)
 * @method static void assertCaptured(?callable $callback = null)
 * @method static void assertCapturedCount(int $count)
 * @method static void assertNothingCaptured()
 * @method static void assertPdfCaptured(?callable $callback = null)
 * @method static void assertPdfCapturedCount(int $count)
 * @method static void assertNoPdfsCaptured()
 * @method static void assertClosed(string $key)
 * @method static void assertNotClosed(string $key)
 * @method static void assertNothingClosed()
 *
 * @see Factory
 */
class Screenshot extends Facade
{
    /**
     * Replace the screenshot factory with a recording fake.
     */
    public static function fake(?ScreenshotResult $result = null, ?PdfResult $pdfResult = null): ScreenshotFake
    {
        static::swap($fake = ScreenshotFake::from(
            factory: static::getFacadeRoot(),
            result: $result,
            pdfResult: $pdfResult,
        ));

        return $fake;
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return Factory::class;
    }
}
