<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\PdfOptions;

test('browser settings reject invalid changes', function ($method, $arguments) {
    expect(fn () => BrowserOptions::make()->{$method}(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([
    'width' => ['setSize', [0, 768]],
    'height' => ['setSize', [1024, 20000]],
    'viewport area' => ['setSize', [10000, 10000]],
    'browser timeout' => ['setBrowserTimeout', [0]],
    'load timeout' => ['setLoadTimeout', [-1]],
    'socket' => ['setSocket', [' ']],
]);

test('invalid browser changes leave both settings unchanged', function () {
    $options = BrowserOptions::make()->setSize(400, 300)->setTimeouts(load: 500, browser: 1000);

    expect(fn () => $options->setSize(800, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => $options->setTimeouts(load: 100, browser: 0))->toThrow(InvalidArgumentException::class);
    expect($options->getWidth())->toBe(400)->and($options->getHeight())->toBe(300)
        ->and($options->getLoadTimeout())->toBe(500)->and($options->getBrowserTimeout())->toBe(1000);
});

test('browser factories return independent instances with default settings', function () {
    $first = BrowserOptions::make()->setSize(400, 300)->setSocket('first')->setJavascript(true);
    $second = BrowserOptions::make();

    expect($first)->not->toBe($second)
        ->and($second->getWidth())->toBe(1024)->and($second->getHeight())->toBe(768)
        ->and($second->getBrowserTimeout())->toBe(60000)->and($second->getLoadTimeout())->toBe(15000)
        ->and($second->getSocket())->toBeNull()->and($second->getJavascript())->toBeFalse()
        ->and($second->getFailOnTimeout())->toBeFalse();
});

test('options require factory construction', function ($class) {
    expect(fn () => new $class)->toThrow(Error::class, 'protected');
})->with([BrowserOptions::class, ImageOptions::class, PdfOptions::class]);

class CustomBrowserOptions extends BrowserOptions
{
    protected int $width = 1200;
}

test('option factories respect subclass defaults', function () {
    $options = CustomBrowserOptions::make();

    expect($options)->toBeInstanceOf(CustomBrowserOptions::class)
        ->and($options->getWidth())->toBe(1200)
        ->and($options->setSize(800, 600))->toBe($options);
    expect(fn () => $options->setSize(0, 600))->toThrow(InvalidArgumentException::class);
});

test('individual timeout setters preserve the other configured value', function ($method, $load, $browser) {
    $options = BrowserOptions::make()->setTimeouts(load: 20000, browser: 80000);

    expect($options->{$method}(30000))->toBe($options)
        ->and($options->getLoadTimeout())->toBe($load)
        ->and($options->getBrowserTimeout())->toBe($browser);
})->with([
    'load' => ['setLoadTimeout', 30000, 80000],
    'browser' => ['setBrowserTimeout', 20000, 30000],
]);

test('invalid individual timeout updates leave both configured values unchanged', function ($method, $value) {
    $options = BrowserOptions::make()->setTimeouts(load: 20000, browser: 80000);

    expect(fn () => $options->{$method}($value))->toThrow(InvalidArgumentException::class);

    expect($options->getLoadTimeout())->toBe(20000)
        ->and($options->getBrowserTimeout())->toBe(80000);
})->with([
    'zero load' => ['setLoadTimeout', 0],
    'negative load' => ['setLoadTimeout', -1],
    'zero browser' => ['setBrowserTimeout', 0],
    'negative browser' => ['setBrowserTimeout', -1],
]);
