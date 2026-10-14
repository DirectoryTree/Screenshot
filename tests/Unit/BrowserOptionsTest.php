<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\PdfOptions;

test('browser settings reject invalid changes', function ($method, $arguments) {
    expect(fn () => (new BrowserOptions)->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([
    'width' => ['setSize', [0, 768]],
    'height' => ['setSize', [1024, 20000]],
    'viewport area' => ['setSize', [10000, 10000]],
    'browser timeout' => ['setBrowserTimeout', [0]],
    'load timeout' => ['setLoadTimeout', [-1]],
    'socket' => ['setSocket', [' ']],
]);

test('invalid browser changes leave both settings unchanged', function () {
    $options = (new BrowserOptions)->setSize(400, 300)->setTimeouts(load: 500, browser: 1000);

    expect(fn () => $options->setSize(800, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => $options->setTimeouts(load: 100, browser: 0))->toThrow(InvalidArgumentException::class);
    expect($options->getWidth())->toBe(400);
    expect($options->getHeight())->toBe(300);
    expect($options->getLoadTimeout())->toBe(500);
    expect($options->getBrowserTimeout())->toBe(1000);
});

test('browser constructors create independent instances with default settings', function () {
    $first = (new BrowserOptions)->setSize(400, 300)
        ->setSocket('first')
        ->setJavascript(true);
    $second = new BrowserOptions;

    expect($first)->not->toBe($second);
    expect($second->getWidth())->toBe(1024);
    expect($second->getHeight())->toBe(768);
    expect($second->getBrowserTimeout())->toBe(60000);
    expect($second->getLoadTimeout())->toBe(15000);
    expect($second->getSocket())->toBeNull();
    expect($second->getJavascript())->toBeFalse();
    expect($second->getFailOnTimeout())->toBeFalse();
});

test('option constructors validate declared defaults', function (Closure $create) {
    expect($create)->toThrow(InvalidArgumentException::class);
})->with([
    'browser' => fn () => new class extends BrowserOptions
    {
        protected int $width = 0;
    },
    'image' => fn () => new class extends ImageOptions
    {
        protected int $maxWidth = 0;
    },
    'PDF' => fn () => new class extends PdfOptions
    {
        protected float $paperWidth = 0;
    },
]);

class CustomBrowserOptions extends BrowserOptions
{
    protected int $width = 1200;
}

test('option constructors respect subclass defaults', function () {
    $options = new CustomBrowserOptions;

    expect($options)->toBeInstanceOf(CustomBrowserOptions::class);
    expect($options->getWidth())->toBe(1200);
    expect($options->setSize(800, 600))->toBe($options);
    expect(fn () => $options->setSize(0, 600))->toThrow(InvalidArgumentException::class);
});

test('individual timeout setters preserve the other configured value', function ($method, $load, $browser) {
    $options = (new BrowserOptions)->setTimeouts(load: 20000, browser: 80000);

    expect($options->{$method}(30000))->toBe($options);
    expect($options->getLoadTimeout())->toBe($load);
    expect($options->getBrowserTimeout())->toBe($browser);
})->with([
    'load' => ['setLoadTimeout', 30000, 80000],
    'browser' => ['setBrowserTimeout', 20000, 30000],
]);

test('invalid individual timeout updates leave both configured values unchanged', function ($method, $value) {
    $options = (new BrowserOptions)->setTimeouts(load: 20000, browser: 80000);

    expect(fn () => $options->{$method}($value))->toThrow(InvalidArgumentException::class);

    expect($options->getLoadTimeout())->toBe(20000);
    expect($options->getBrowserTimeout())->toBe(80000);
})->with([
    'zero load' => ['setLoadTimeout', 0],
    'negative load' => ['setLoadTimeout', -1],
    'zero browser' => ['setBrowserTimeout', 0],
    'negative browser' => ['setBrowserTimeout', -1],
]);
