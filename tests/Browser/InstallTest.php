<?php

use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\Support\ChromeInstaller;
use DirectoryTree\Screenshot\Tests\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function Pest\Laravel\artisan;

uses(TestCase::class)->group('browser');

test('the install command downloads a browser that the application can capture with', function () {
    $directory = sys_get_temp_dir().'/screenshot-install-browser-'.bin2hex(random_bytes(8));
    config([
        'screenshot.browser.binary' => null,
        'screenshot.browser.directory' => $directory,
        'screenshot.browser.options' => ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    ]);

    try {
        artisan('screenshot:install')->assertSuccessful();
        expect(app()->make(ChromeInstaller::class)->path())->not->toBeNull();

        $result = Screenshot::html(file_get_contents(__DIR__.'/../Fixtures/document.html'))
            ->size(width: 640, height: 480)->capture();

        expect($result->width())->toBe(640)
            ->and($result->height())->toBe(960)
            ->and($result->mimeType())->toBe('image/png');
    } finally {
        (new Filesystem)->remove($directory);
    }
})->skip(fn () => getenv('SCREENSHOT_TEST_INSTALL') !== '1', 'Set SCREENSHOT_TEST_INSTALL=1 to download Chrome from Google.');
