<?php

use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\Support\ChromeInstaller;
use DirectoryTree\Screenshot\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
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
        $this->withoutMockingConsoleOutput();
        expect(artisan('screenshot:install'))->toBe(0, Artisan::output());
        $installer = app()->make(ChromeInstaller::class);
        $path = $installer->path();
        expect($path)->not->toBeNull();
        $status = artisan('screenshot:install');
        $output = Artisan::output();
        expect($status)->toBe(0, $output);
        expect($output)->toContain('Chrome is already installed.')
            ->and($installer->path())->toBe($path);
        $installed = json_decode(file_get_contents($directory.'/installed.json'), true);
        expect($installer->install($installed['version']))->toBe($path);

        $result = Screenshot::html(file_get_contents(__DIR__.'/../Fixtures/document.html'))
            ->size(width: 640, height: 480)->capture();

        expect($result->width())->toBe(640)
            ->and($result->height())->toBe(960)
            ->and($result->mimeType())->toBe('image/png');

        $pdf = Screenshot::html('<h1>Installed Chrome PDF</h1>')->pdf()->capture();
        expect($pdf->mimeType())->toBe('application/pdf')->and($pdf->contents())->toStartWith('%PDF-');

        $jpeg = Screenshot::html('<h1>Installed Chrome</h1>')->size(320, 240)->jpeg()->viewport()->capture();
        expect($jpeg->width())->toBe(320)
            ->and($jpeg->height())->toBe(240)
            ->and($jpeg->mimeType())->toBe('image/jpeg');
    } finally {
        (new Filesystem)->remove($directory);
    }
})->skip(fn () => getenv('SCREENSHOT_TEST_INSTALL') !== '1', 'Set SCREENSHOT_TEST_INSTALL=1 to download Chrome from Google.');
