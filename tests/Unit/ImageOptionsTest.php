<?php

use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\ImageOptions;

test('format and quality updates reject invalid input without changing either value', function () {
    $options = (new ImageOptions)->setFormat(OutputFormat::Png, 85);

    expect(fn () => $options->setFormat('gif', 50))->toThrow(TypeError::class);
    expect($options->getFormat())->toBe(OutputFormat::Png)->and($options->getQuality())->toBe(85);

    expect(fn () => $options->setFormat(OutputFormat::Jpeg, 101))->toThrow(InvalidArgumentException::class);
    expect($options->getFormat())->toBe(OutputFormat::Png)->and($options->getQuality())->toBe(85);
});

test('changing format preserves quality unless explicitly supplied', function () {
    $options = new ImageOptions;
    $options->setFormat(OutputFormat::Jpeg, 0)->setFormat(OutputFormat::Png);

    expect($options->getFormat())->toBe(OutputFormat::Png)->and($options->getQuality())->toBe(0);

    $options->setFormat(OutputFormat::Jpeg, 100);
    expect($options->getFormat())->toBe(OutputFormat::Jpeg)->and($options->getQuality())->toBe(100);
});

test('dimension pairs can change together at the maximum allowed area', function () {
    $options = (new ImageOptions)->setMaxSize(4000, 10000);
    $options->setMaxSize(10000, 4000);

    expect($options->getMaxWidth())->toBe(10000)->and($options->getMaxHeight())->toBe(4000);
});
