<?php

use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\Tests\TestCase;

uses(TestCase::class)->group('browser');

test('the manager captures images and PDFs through the configured Chrome driver', function () {
    config([
        'screenshot.browser.binary' => getenv('CHROME_PATH') ?: null,
        'screenshot.browser.options' => ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
        'screenshot.capture.width' => 640,
        'screenshot.capture.height' => 480,
        'screenshot.image.full_page' => false,
    ]);

    $image = Screenshot::html('<h1>Default driver</h1>')->capture();
    $pdf = Screenshot::driver('chrome')->view('card', ['name' => 'Steve'])->pdf()->capture();

    expect($image->width())->toBe(640);
    expect($image->height())->toBe(480);
    expect($image->mimeType())->toBe('image/png');
    expect($pdf->contents())->toStartWith('%PDF-');
});
