<?php

use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use HeadlessChromium\BrowserFactory;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

uses()->group('browser');

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/screenshot-shared-'.bin2hex(random_bytes(8));
    mkdir($this->directory, 0700);
    $this->driver = new ChromeDriver(getenv('CHROME_PATH') ?: null, [
        'noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1',
    ], $this->directory);
    $this->factory = new Screenshot($this->driver);
});

afterEach(function () {
    foreach (glob($this->directory.'/*.socket') as $path) {
        $this->factory->closeSocketPath($path);
    }

    // Chrome finishes flushing its profile asynchronously after Browser.close.
    // Tolerate files disappearing during cleanup, then verify removal completed.
    set_error_handler(static fn () => true);

    try {
        $deadline = microtime(true) + 5;
        while (is_dir($this->directory) && microtime(true) < $deadline) {
            (new Filesystem)->deleteDirectory($this->directory);
            clearstatcache();
            usleep(10000);
        }
    } finally {
        restore_error_handler();
    }

    expect(is_dir($this->directory))->toBeFalse();
});

test('separate workers start one shared browser and capture concurrently with independent settings', function () {
    $processes = [];

    try {
        foreach (['first' => 320, 'second' => 640] as $id => $width) {
            $process = new Process([PHP_BINARY, __DIR__.'/../Fixtures/parallel-worker.php', $this->directory, $id, (string) $width]);
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 15;
        while (count(glob($this->directory.'/*.ready')) !== 2 && microtime(true) < $deadline) {
            usleep(10000);
        }

        expect(glob($this->directory.'/*.ready'))->toHaveCount(2);
        file_put_contents($this->directory.'/release', 'go');

        foreach ($processes as $process) {
            $process->wait();
            expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
        }

        $first = json_decode(file_get_contents($this->directory.'/first.json'), true);
        $second = json_decode(file_get_contents($this->directory.'/second.json'), true);

        expect($first['socket'])->toBe($second['socket'])
            ->and($first['width'])->toBe(320)->and($first['height'])->toBe(240)
            ->and($second['width'])->toBe(640)->and($second['height'])->toBe(600);

        // Both worker processes have exited; the same browser remains reusable.
        $result = $this->factory->html('third')->socket('parallel')->size(500, 300)->capture();
        expect($result->width())->toBe(500)
            ->and(file_get_contents(BrowserSocket::named('parallel', $this->directory)->path()))->toBe($first['socket']);

        $browser = BrowserFactory::connectToBrowser($first['socket']);
        try {
            expect(count($browser->getPages()))->toBeLessThanOrEqual(1); // Some Chrome builds retain an initial blank tab.
        } finally {
            $browser->getConnection()->disconnect();
        }
    } finally {
        file_put_contents($this->directory.'/release', 'go');
        foreach ($processes as $process) {
            $process->stop();
        }
    }
});

test('explicit socket paths are reusable and dead browsers are replaced', function () {
    $path = $this->directory.'/explicit.socket';
    $this->factory->html('first')->socketPath($path)->capture();
    $first = file_get_contents($path);
    $this->factory->html('second')->socketPath($path)->capture();
    expect(file_get_contents($path))->toBe($first);

    $browser = BrowserFactory::connectToBrowser($first);
    $browser->close();
    $browser->getConnection()->disconnect();

    $this->factory->html('replacement')->socketPath($path)->timeout(1000)->capture();
    expect(file_get_contents($path))->not->toBe($first);

    $this->factory->closeSocketPath($path);
    expect(file_exists($path))->toBeFalse();

    $this->factory->html('after shutdown')->socketPath($path)->capture();
    expect(file_get_contents($path))->not->toBe($first);
});

test('different keys select different browsers', function () {
    $this->factory->html('first')->socket('first')->capture();
    $this->factory->html('second')->socket('second')->capture();
    $first = BrowserSocket::named('first', $this->directory)->path();
    $second = BrowserSocket::named('second', $this->directory)->path();

    expect(file_get_contents($first))->not->toBe(file_get_contents($second));
    $this->factory->closeSocket('first');
    expect(file_exists($first))->toBeFalse()->and(file_exists($second))->toBeTrue();
});
