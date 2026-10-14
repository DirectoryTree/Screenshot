<?php

use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\OutputFormat;

test('format and quality updates reject invalid input without changing either value', function () {
    $options = new ImageOptions(quality: 85);

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
    $options = new ImageOptions(width: 10000, height: 4000, maxWidth: 4000, maxHeight: 10000);
    $options->setSize(4000, 10000)->setMaxSize(10000, 4000);

    expect($options->getWidth())->toBe(4000)->and($options->getHeight())->toBe(10000)
        ->and($options->getMaxWidth())->toBe(10000)->and($options->getMaxHeight())->toBe(4000);
});

test('individual timeout setters preserve the other configured value', function ($method, $load, $browser) {
    $options = new ImageOptions(browserTimeout: 80000, loadTimeout: 20000);

    expect($options->{$method}(30000))->toBe($options)
        ->and($options->getLoadTimeout())->toBe($load)
        ->and($options->getBrowserTimeout())->toBe($browser);
})->with([
    'load' => ['setLoadTimeout', 30000, 80000],
    'browser' => ['setBrowserTimeout', 20000, 30000],
]);

test('invalid individual timeout updates leave both configured values unchanged', function ($method, $value) {
    $options = new ImageOptions(browserTimeout: 80000, loadTimeout: 20000);

    expect(fn () => $options->{$method}($value))->toThrow(InvalidArgumentException::class);

    expect($options->getLoadTimeout())->toBe(20000)
        ->and($options->getBrowserTimeout())->toBe(80000);
})->with([
    'zero load' => ['setLoadTimeout', 0],
    'negative load' => ['setLoadTimeout', -1],
    'zero browser' => ['setBrowserTimeout', 0],
    'negative browser' => ['setBrowserTimeout', -1],
]);
