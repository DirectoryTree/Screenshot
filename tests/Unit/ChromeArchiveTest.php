<?php

use DirectoryTree\Screenshot\Support\ChromeArchive;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    test()->screenshotDirectory = null;
});

afterEach(function () {
    (new Filesystem)->remove(temporaryDirectory());
});

test('archives preserve executable permissions and framework symbolic links', function () {
    $zip = new ZipArchive;

    $zip->open(temporaryDirectory().'/chrome.zip', ZipArchive::CREATE);
    $zip->addFromString('Framework/Versions/A/Chrome', 'binary');
    $zip->setExternalAttributesIndex(0, ZipArchive::OPSYS_UNIX, 0100755 << 16);
    $zip->addFromString('Framework/Versions/Current', 'A');
    $zip->setExternalAttributesIndex(1, ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->addFromString('Framework/Chrome', 'Versions/Current/Chrome');
    $zip->setExternalAttributesIndex(2, ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();

    (new ChromeArchive)->extract(temporaryDirectory().'/chrome.zip', temporaryDirectory().'/extracted');

    expect(is_link(temporaryDirectory().'/extracted/Framework/Chrome'))->toBeTrue();
    expect(file_get_contents(temporaryDirectory().'/extracted/Framework/Chrome'))->toBe('binary');
    expect(is_executable(temporaryDirectory().'/extracted/Framework/Chrome'))->toBeTrue();
});

test('unsafe archive entries cannot escape the extraction directory', function ($name, $contents, $mode) {
    $zip = new ZipArchive;

    $zip->open(temporaryDirectory().'/chrome.zip', ZipArchive::CREATE);
    $zip->addFromString($name, $contents);
    $zip->setExternalAttributesIndex(0, ZipArchive::OPSYS_UNIX, $mode << 16);
    $zip->close();

    expect(
        fn () => (new ChromeArchive)->extract(temporaryDirectory().'/chrome.zip', temporaryDirectory().'/extracted'),
    )->toThrow(RuntimeException::class, 'unsafe');
    expect(file_exists(temporaryDirectory().'/escaped'))->toBeFalse();
})->with([
    ['../escaped', 'file', 0100644],
    ['/absolute', 'file', 0100644],
    ['C:\\escaped', 'file', 0100644],
    ['link', '../escaped', 0120777],
    ['link', '/absolute', 0120777],
]);
