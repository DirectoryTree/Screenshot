<?php

use DirectoryTree\Screenshot\ImageResult;

test('results expose image bytes and metadata', function () {
    $result = new ImageResult(imageFixture());

    expect($result->base64())->toBe(imageFixture());
    expect($result->contents())->toBe(base64_decode(imageFixture()));
    expect($result->mimeType())->toBe('image/png');
    expect($result->dataUri())->toBe('data:image/png;base64,'.imageFixture());
    expect($result->width())->toBe(1);
    expect($result->height())->toBe(1);
});

test('invalid image data is rejected', function ($data) {
    expect(fn () => new ImageResult($data))->toThrow(InvalidArgumentException::class);
})->with(['', 'not base64!', base64_encode('hello'), base64_encode("\x89PNG"), base64_encode('GIF89a')]);

test('results can be saved to a local file', function () {
    $path = tempnam(sys_get_temp_dir(), 'screenshot-test-');

    try {
        $result = new ImageResult(imageFixture());

        expect($result->save($path))->toBe($result);
        expect(file_get_contents($path))->toBe($result->contents());
    } finally {
        unlink($path);
    }
});

test('failed local writes throw an exception', function () {
    expect(fn () => (new ImageResult(imageFixture()))->save('/missing-screenshot-directory/image.png'))
        ->toThrow(RuntimeException::class, 'Unable to save');
});
