<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\Screenshot;
use HeadlessChromium\Exception\OperationTimedOut;
use Symfony\Component\Process\Process;

uses()->group('browser');

function realScreenshot(): Screenshot
{
    return new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    ));
}

test('real Chromium renders a full page PNG from local HTML', function () {
    $result = realScreenshot()->html(file_get_contents(__DIR__.'/../Fixtures/document.html'))
        ->size(640, 480)->capture();

    expect($result->width())->toBe(640)
        ->and($result->height())->toBe(960)
        ->and($result->mimeType())->toBe('image/png');

    if (getenv('SCREENSHOT_ARTIFACT_PATH')) {
        $result->save(getenv('SCREENSHOT_ARTIFACT_PATH'));
    }
});

test('real Chromium applies configured defaults and per-capture dimension limits', function () {
    $factory = new Screenshot(
        driver: new ChromeDriver(getenv('CHROME_PATH') ?: null, ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1']),
        browser: (new BrowserOptions)->setSize(640, 480),
        image: (new ImageOptions)->setFullPage(false)->setFormat(OutputFormat::Jpeg, 85),
    );
    $result = $factory->html(file_get_contents(__DIR__.'/../Fixtures/document.html'))
        ->jpeg()->maxSize(500, 300)->capture();

    expect($result->width())->toBe(500)
        ->and($result->height())->toBe(300)
        ->and($result->mimeType())->toBe('image/jpeg');
});

test('real Chromium disables JavaScript by default and permits opting in', function () {
    $html = '<style>html,body{margin:0}div{height:200px}</style><div id="content"></div>'
        .'<script>document.getElementById("content").style.height="900px"</script>';

    $disabled = realScreenshot()->html($html)->size(400, 300)->capture();
    $enabled = realScreenshot()->html($html)->size(400, 300)->javascript()->capture();

    expect($disabled->height())->toBe(300)
        ->and($enabled->height())->toBe(900);
});

test('real Chromium bounds oversized full page documents', function () {
    $result = realScreenshot()->html('<style>body{margin:0;width:5000px;height:15000px}</style>')
        ->png()->maxSize(700, 800)->capture();

    expect($result->width())->toBe(700)->and($result->height())->toBe(800);
});

test('real Chromium captures local URLs and handles slow resources', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);

    $server = new Process([
        PHP_BINARY, '-S', $address, __DIR__.'/../Fixtures/server.php',
    ]);
    $server->start();

    try {
        $server->waitUntil(fn ($type, $output) => str_contains($output, 'Development Server'));
        $url = 'http://'.$address;
        $result = realScreenshot()->url($url)->size(640, 480)->capture();
        expect($result->width())->toBe(640)->and($result->height())->toBe(960);

        $html = '<h1>Partial content</h1><img src="'.$url.'/slow">';
        $partial = realScreenshot()->html($html)->size(400, 300)->timeouts(load: 100)->capture();
        expect($partial->width())->toBe(400)->and($partial->mimeType())->toBe('image/png');

        expect(fn () => realScreenshot()->html($html)->timeouts(load: 100)->failOnTimeout()->capture())
            ->toThrow(OperationTimedOut::class);
    } finally {
        $server->stop();
    }
});
