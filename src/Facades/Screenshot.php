<?php

namespace DirectoryTree\Screenshot\Facades;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\PendingScreenshot;
use DirectoryTree\Screenshot\Screenshot as Factory;
use DirectoryTree\Screenshot\ScreenshotResult;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingScreenshot html(string $html)
 * @method static PendingScreenshot url(string $url)
 * @method static PendingScreenshot view(string $view, array $data = [])
 * @method static void close(string $key)
 * @method static void assertCaptured(?callable $callback = null)
 * @method static void assertCapturedCount(int $count)
 * @method static void assertNothingCaptured()
 *
 * @see Factory
 */
class Screenshot extends Facade
{
    /**
     * Replace the screenshot factory with a recording fake.
     */
    public static function fake(?ScreenshotResult $result = null): ScreenshotFake
    {
        static::swap($fake = new ScreenshotFake(
            $result,
            static::$app['view'],
            new CaptureOptions(...static::$app['config']->get('screenshot.defaults', [])),
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
