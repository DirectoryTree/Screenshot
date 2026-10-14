<?php

namespace DirectoryTree\Screenshot\Tests;

use DirectoryTree\Screenshot\ScreenshotServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ScreenshotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [__DIR__.'/Fixtures']);
    }
}
