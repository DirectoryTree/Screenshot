<?php

use DirectoryTree\Screenshot\Support\ChromeArchive;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/screenshot-archive-'.bin2hex(random_bytes(8));
    (new Filesystem)->mkdir($this->directory);
});

afterEach(function () {
    (new Filesystem)->remove($this->directory);
});

test('archives preserve executable permissions and framework symbolic links', function () {
    $zip = new ZipArchive;
    $zip->open($this->directory.'/chrome.zip', ZipArchive::CREATE);
    $zip->addFromString('Framework/Versions/A/Chrome', 'binary');
    $zip->setExternalAttributesIndex(0, ZipArchive::OPSYS_UNIX, 0100755 << 16);
    $zip->addFromString('Framework/Versions/Current', 'A');
    $zip->setExternalAttributesIndex(1, ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->addFromString('Framework/Chrome', 'Versions/Current/Chrome');
    $zip->setExternalAttributesIndex(2, ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();

    (new ChromeArchive)->extract($this->directory.'/chrome.zip', $this->directory.'/extracted');

    expect(is_link($this->directory.'/extracted/Framework/Chrome'))->toBeTrue()
        ->and(file_get_contents($this->directory.'/extracted/Framework/Chrome'))->toBe('binary')
        ->and(is_executable($this->directory.'/extracted/Framework/Chrome'))->toBeTrue();
});

test('unsafe archive entries cannot escape the extraction directory', function ($name, $contents, $mode) {
    $zip = new ZipArchive;
    $zip->open($this->directory.'/chrome.zip', ZipArchive::CREATE);
    $zip->addFromString($name, $contents);
    $zip->setExternalAttributesIndex(0, ZipArchive::OPSYS_UNIX, $mode << 16);
    $zip->close();

    expect(fn () => (new ChromeArchive)->extract($this->directory.'/chrome.zip', $this->directory.'/extracted'))
        ->toThrow(RuntimeException::class, 'unsafe');
    expect(file_exists($this->directory.'/escaped'))->toBeFalse();
})->with([
    ['../escaped', 'file', 0100644],
    ['/absolute', 'file', 0100644],
    ['C:\\escaped', 'file', 0100644],
    ['link', '../escaped', 0120777],
    ['link', '/absolute', 0120777],
]);
