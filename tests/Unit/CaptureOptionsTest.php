<?php

use DirectoryTree\Screenshot\CaptureOptions;

test('format and quality updates reject invalid input without changing either value', function () {
    $options = new CaptureOptions(quality: 85);

    expect(fn () => $options->setFormat('gif', 50))->toThrow(InvalidArgumentException::class);
    expect($options->getFormat())->toBe('png')->and($options->getQuality())->toBe(85);

    expect(fn () => $options->setFormat('jpeg', 101))->toThrow(InvalidArgumentException::class);
    expect($options->getFormat())->toBe('png')->and($options->getQuality())->toBe(85);
});

test('changing format preserves quality unless explicitly supplied', function () {
    $options = new CaptureOptions;
    $options->setFormat('jpeg', 0)->setFormat('png');

    expect($options->getFormat())->toBe('png')->and($options->getQuality())->toBe(0);

    $options->setFormat('jpeg', 100);
    expect($options->getFormat())->toBe('jpeg')->and($options->getQuality())->toBe(100);
});

test('dimension pairs can change together at the maximum allowed area', function () {
    $options = new CaptureOptions(width: 10000, height: 4000, maxWidth: 4000, maxHeight: 10000);
    $options->setSize(4000, 10000)->setMaxSize(10000, 4000);

    expect($options->getWidth())->toBe(4000)->and($options->getHeight())->toBe(10000)
        ->and($options->getMaxWidth())->toBe(10000)->and($options->getMaxHeight())->toBe(4000);
});
