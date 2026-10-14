<?php

use DirectoryTree\Screenshot\ScreenshotResult;

test('results expose image bytes and metadata', function () {
    $result = new ScreenshotResult(imageFixture());

    expect($result->base64())->toBe(imageFixture())
        ->and($result->contents())->toBe(base64_decode(imageFixture()))
        ->and($result->mimeType())->toBe('image/png')
        ->and($result->dataUri())->toBe('data:image/png;base64,'.imageFixture())
        ->and($result->width())->toBe(1)
        ->and($result->height())->toBe(1);
});

test('invalid image data is rejected', function ($data) {
    expect(fn () => new ScreenshotResult($data))->toThrow(InvalidArgumentException::class);
})->with(['', 'not base64!', base64_encode('hello'), base64_encode("\x89PNG"), base64_encode('GIF89a')]);

test('results can be saved to a local file', function () {
    $path = tempnam(sys_get_temp_dir(), 'screenshot-test-');

    try {
        $result = new ScreenshotResult(imageFixture());
        expect($result->save($path))->toBe($result)
            ->and(file_get_contents($path))->toBe($result->contents());
    } finally {
        unlink($path);
    }
});

test('failed local writes throw an exception', function () {
    expect(fn () => (new ScreenshotResult(imageFixture()))->save('/missing-screenshot-directory/image.png'))
        ->toThrow(RuntimeException::class, 'Unable to save');
});
