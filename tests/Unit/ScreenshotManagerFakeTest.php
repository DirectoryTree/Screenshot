<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use DirectoryTree\Screenshot\Testing\ScreenshotManagerFake;

test('a manager fake works without a container and can reset its recorder while retaining defaults', function () {
    $fake = new ScreenshotManagerFake(new ScreenshotFake(
        browser: (new BrowserOptions)->setSize(900, 600),
    ));

    $fake->html('default')->capture();
    $fake->driver('remote')->html('remote')->size(320, 240)->capture();
    $fake->close(key: 'invoices');

    $fake->assertCapturedCount(count: 2);
    $fake->assertClosed(key: 'invoices');

    $result = new ImageResult(imageFixture());
    $fresh = $fake->fake($result);

    $fresh->assertNothingCaptured();
    $fresh->assertNothingClosed();
    expect($fresh->driver('chrome')->html('fresh')->capture())->toBe($result);
    $fresh->assertCaptured(fn ($source, $type, $options) => $source === 'fresh'
        && $options->browser()->getWidth() === 900
        && $options->browser()->getHeight() === 600);
    $fresh->assertCapturedCount(1);
    $fake->assertCapturedCount(2);
});
