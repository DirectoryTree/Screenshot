<?php

use DirectoryTree\Screenshot\Testing\ScreenshotFake;
use PHPUnit\Framework\AssertionFailedError;

test('shutdown requests match exact keys and are separate from captures', function () {
    $fake = new ScreenshotFake;
    $fake->assertNotClosed('emails');
    $fake->close('emails');
    $fake->close('01');
    $fake->close('emails');

    $fake->assertClosed('emails');
    $fake->assertClosed('01');
    $fake->assertNotClosed('1');
    $fake->assertNotClosed('Emails');
    $fake->assertNothingCaptured();
});

test('assertClosed fails when the requested key was not closed', function () {
    $fake = new ScreenshotFake;
    $fake->close('invoices');

    expect(fn () => $fake->assertClosed('emails'))
        ->toThrow(AssertionFailedError::class, 'The expected browser [emails] was not closed.');
});

test('assertNotClosed fails when the requested key was closed', function () {
    $fake = new ScreenshotFake;
    $fake->close('emails');

    expect(fn () => $fake->assertNotClosed('emails'))
        ->toThrow(AssertionFailedError::class, 'The unexpected browser [emails] was closed.');
});

test('assertNothingClosed fails when any shutdown was requested', function () {
    $fake = new ScreenshotFake;
    $fake->close('emails');

    expect(fn () => $fake->assertNothingClosed())
        ->toThrow(AssertionFailedError::class, 'Unexpected browser shutdowns were requested.');
});
