<?php

namespace DirectoryTree\Screenshot\Facades;

use Closure;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\PendingSource;
use DirectoryTree\Screenshot\Screenshot as Factory;
use DirectoryTree\Screenshot\ScreenshotManager;
use DirectoryTree\Screenshot\Testing\ScreenshotManagerFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Factory driver(?string $driver = null)
 * @method static ScreenshotManager extend(string $driver, Closure $callback)
 * @method static PendingSource html(string $html)
 * @method static PendingSource url(string $url)
 * @method static PendingSource view(string $view, array $data = [])
 * @method static void close(string $key)
 * @method static void assertCaptured(?callable $callback = null)
 * @method static void assertCapturedCount(int $count)
 * @method static void assertNothingCaptured()
 * @method static void assertClosed(string $key)
 * @method static void assertNotClosed(string $key)
 * @method static void assertNothingClosed()
 *
 * @see ScreenshotManager
 */
class Screenshot extends Facade
{
    /**
     * Replace the screenshot manager with a recording fake.
     */
    public static function fake(ImageResult|PdfResult|null $result = null): ScreenshotManagerFake
    {
        static::swap($fake = static::getFacadeRoot()->fake($result));

        return $fake;
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ScreenshotManager::class;
    }
}
