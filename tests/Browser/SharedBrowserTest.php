<?php

use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\CommunicationException;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

uses()->group('browser');

beforeEach(function () {
    test()->screenshotDirectory = null;
});

afterEach(function () {
    foreach (glob(temporaryDirectory().'/*.socket') as $path) {
        (new BrowserSocket($path))->close();
    }

    // Chrome finishes flushing its profile asynchronously after Browser.close.
    // Tolerate files disappearing during cleanup, then verify removal completed.
    set_error_handler(static fn () => true);

    try {
        $deadline = microtime(true) + 5;
        while (is_dir(temporaryDirectory()) && microtime(true) < $deadline) {
            (new Filesystem)->deleteDirectory(temporaryDirectory());
            clearstatcache();
            usleep(10000);
        }
    } finally {
        restore_error_handler();
    }

    expect(is_dir(temporaryDirectory()))->toBeFalse();
});

/**
 * Create a screenshot factory using the current test's socket directory.
 */
function sharedScreenshot(): Screenshot
{
    return new Screenshot(new ChromeDriver(getenv('CHROME_PATH') ?: null, [
        'noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1',
    ], temporaryDirectory()));
}

test('separate workers start one shared browser and capture concurrently with independent settings', function ($secondFormat) {
    $factory = sharedScreenshot();
    $processes = [];

    try {
        foreach (['first' => 320, 'second' => 640] as $id => $width) {
            $process = new Process([PHP_BINARY, __DIR__.'/../Fixtures/parallel-worker.php', temporaryDirectory(), $id, (string) $width, $id === 'second' ? $secondFormat : 'png']);
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 15;
        while (count(glob(temporaryDirectory().'/*.ready')) !== 2 && microtime(true) < $deadline) {
            usleep(10000);
        }

        expect(glob(temporaryDirectory().'/*.ready'))->toHaveCount(2);
        file_put_contents(temporaryDirectory().'/release', 'go');

        foreach ($processes as $process) {
            $process->wait();
            expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
        }

        $first = json_decode(file_get_contents(temporaryDirectory().'/first.json'), true);
        $second = json_decode(file_get_contents(temporaryDirectory().'/second.json'), true);

        expect($first['socket'])->toBe($second['socket'])
            ->and($first['width'])->toBe(320)->and($first['height'])->toBe(240);

        if ($secondFormat === 'pdf') {
            expect($second['mime'])->toBe('application/pdf');
        } else {
            expect($second['width'])->toBe(640)->and($second['height'])->toBe(600);
        }

        // Both worker processes have exited; the same browser remains reusable.
        $result = $factory->html('third')->socket('parallel')->size(500, 300)->capture();
        expect($result->width())->toBe(500)
            ->and(file_get_contents(BrowserSocket::named('parallel', temporaryDirectory())->path()))->toBe($first['socket']);

        $browser = BrowserFactory::connectToBrowser($first['socket']);
        try {
            expect(count($browser->getPages()))->toBeLessThanOrEqual(1); // Some Chrome builds retain an initial blank tab.
        } finally {
            $browser->getConnection()->disconnect();
        }
    } finally {
        file_put_contents(temporaryDirectory().'/release', 'go');
        foreach ($processes as $process) {
            $process->stop();
        }
    }
})->with(['png', 'pdf']);

test('named sockets are reusable and dead browsers are replaced', function () {
    $factory = sharedScreenshot();
    $path = BrowserSocket::named('reusable', temporaryDirectory())->path();
    $factory->html('first')->socket('reusable')->capture();
    $first = file_get_contents($path);
    $factory->html('second')->socket('reusable')->capture();
    expect(file_get_contents($path))->toBe($first);

    $factory->close('reusable');
    file_put_contents($path, $first); // Simulate a saved endpoint left behind by a dead browser.

    $factory->html('replacement')->socket('reusable')->timeouts(browser: 10000)->capture();
    expect(file_get_contents($path))->not->toBe($first);

    $factory->close('reusable');
    expect(file_exists($path))->toBeFalse();

    $factory->html('after shutdown')->socket('reusable')->capture();
    expect(file_get_contents($path))->not->toBe($first);
});

test('different keys select different browsers', function () {
    $factory = sharedScreenshot();
    $factory->html('first')->socket('first')->capture();
    $factory->html('second')->socket('second')->capture();
    $first = BrowserSocket::named('first', temporaryDirectory())->path();
    $second = BrowserSocket::named('second', temporaryDirectory())->path();

    expect(file_get_contents($first))->not->toBe(file_get_contents($second));
    $factory->close('first');
    expect(file_exists($first))->toBeFalse()->and(file_exists($second))->toBeTrue();
});

test('failed page initialization does not leak tabs or close another workers page', function () {
    $factory = sharedScreenshot();
    $factory->html('warmup')->socket('cleanup')->capture();
    $uri = file_get_contents(BrowserSocket::named('cleanup', temporaryDirectory())->path());
    $browser = BrowserFactory::connectToBrowser($uri);
    $page = $browser->createPage();

    try {
        $page->setHtml('<h1>Another worker</h1>');
        $targets = fn () => array_column(array_filter($browser->getConnection()
            ->sendMessageSync(new Message('Target.getTargets'))['result']['targetInfos'],
            fn ($target) => $target['type'] === 'page'), 'targetId');
        $original = $targets();
        $broken = new Screenshot(new ChromeDriver(getenv('CHROME_PATH') ?: null, [
            'headers' => ['X-Test' => ['invalid']],
        ], temporaryDirectory()));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            expect(fn () => $broken->html('failure')->socket('cleanup')->capture())
                ->toThrow(CommunicationException::class, 'Invalid header value');
            expect($targets())->toEqualCanonicalizing($original);
        }

        expect($page->getHtml())->toContain('Another worker');
        expect($factory->html('recovered')->socket('cleanup')->capture()->mimeType())->toBe('image/png');
        expect(file_get_contents(BrowserSocket::named('cleanup', temporaryDirectory())->path()))->toBe($uri);
    } finally {
        $page->close();
        $browser->getConnection()->disconnect();
    }
});
