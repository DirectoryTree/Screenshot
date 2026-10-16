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
 * Get the dimensions of the first PDF page in points.
 */
function pdfPageSize(string $contents): array
{
    preg_match('~/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]~', $contents, $matches);

    return [(float) ($matches[1] ?? 0), (float) ($matches[2] ?? 0)];
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
