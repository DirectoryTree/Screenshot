<?php

use DirectoryTree\Screenshot\Tests\TestCase;

uses(TestCase::class)->in('Feature');

afterEach(function () {
    Mockery::close();
});

function imageFixture(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=';
}

/**
 * Get a path within an isolated temporary directory for the current test.
 */
function temporaryDirectory(string $path = ''): string
{
    if (test()->screenshotDirectory === null) {
        test()->screenshotDirectory = sys_get_temp_dir().'/screenshot-test-'.bin2hex(random_bytes(8));
        mkdir(test()->screenshotDirectory, 0700);
    }

    return test()->screenshotDirectory.($path === '' ? '' : '/'.$path);
}
