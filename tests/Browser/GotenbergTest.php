<?php

use DirectoryTree\Screenshot\Drivers\GotenbergDriver;
use DirectoryTree\Screenshot\Screenshot;

uses()->group('gotenberg');

function gotenbergScreenshot(bool $authenticate = true): Screenshot
{
    $url = getenv('SCREENSHOT_GOTENBERG_URL');

    if (! $url) {
        test()->markTestSkipped('Set SCREENSHOT_GOTENBERG_URL to run Gotenberg integration tests.');
    }

    $httpOptions = [];

    if ($authenticate && ($username = getenv('SCREENSHOT_GOTENBERG_USERNAME'))) {
        $httpOptions['auth'] = [$username, getenv('SCREENSHOT_GOTENBERG_PASSWORD')];
    }

    return new Screenshot(
        new GotenbergDriver($url, $httpOptions),
    );
}

test('real Gotenberg renders full-page PNG and viewport JPEG captures', function () {
    $source = gotenbergScreenshot()->html(file_get_contents(__DIR__.'/../Fixtures/document.html'))
        ->javascript()
        ->size(640, 480);

    $image = $source->png()->capture();

    expect($image->mimeType())->toBe('image/png');
    expect($image->width())->toBe(640);
    expect($image->height())->toBe(960);

    $viewport = $source->jpeg(73)
        ->viewport()
        ->capture();

    expect($viewport->mimeType())->toBe('image/jpeg');
    expect($viewport->width())->toBe(640);
    expect($viewport->height())->toBe(480);
});

test('real Gotenberg crops images without changing responsive layout', function (string $format) {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('Cropping requires GD.');
    }

    $result = gotenbergScreenshot()->html(<<<'HTML'
        <style>
            html, body { margin: 0; width: 900px; height: 1200px; background: rgb(255, 0, 0); }
            @media (min-width: 600px) {
                html, body { background: rgb(0, 255, 0); }
            }
            .marker { position: absolute; left: 80px; top: 0; width: 20px; height: 20px; background: black; }
        </style>
        <div class="marker"></div>
        HTML)
        ->javascript()
        ->size(640, 480)
        ->{$format}()
        ->maxSize(100, 200)
        ->capture();

    expect($result->width())->toBe(100);
    expect($result->height())->toBe(200);

    $image = imagecreatefromstring($result->contents());
    $color = imagecolorsforindex($image, imagecolorat($image, 0, 0));

    expect($color['green'])->toBeGreaterThan(250);
    expect($color['red'])->toBeLessThan(5);

    $marker = imagecolorsforindex($image, imagecolorat($image, 90, 10));

    expect($marker['green'])->toBeLessThan(5);
})->with(['png', 'jpeg']);

test('real Gotenberg applies the configured service JavaScript setting', function () {
    $url = getenv('SCREENSHOT_GOTENBERG_NOJS_URL');

    if (! $url) {
        $this->markTestSkipped('Set SCREENSHOT_GOTENBERG_NOJS_URL to test a service with JavaScript disabled.');
    }

    $html = '<style>html,body{margin:0}div{height:200px}</style><div id="content"></div>'
        .'<script>document.getElementById("content").style.height="900px"</script>';
    $factory = new Screenshot(
        new GotenbergDriver(url: $url, javascript: false),
    );
    $disabled = $factory->html($html)
        ->size(400, 300)
        ->capture();
    $enabled = gotenbergScreenshot()->html($html)
        ->javascript()
        ->size(400, 300)
        ->capture();

    expect($disabled->height())->toBe(300);
    expect($enabled->height())->toBe(900);
});

test('real Gotenberg rejects requests without the configured service authentication', function () {
    if (! getenv('SCREENSHOT_GOTENBERG_USERNAME')) {
        $this->markTestSkipped('Configure service authentication to test unauthorized requests.');
    }

    $factory = gotenbergScreenshot(authenticate: false);

    expect(
        fn () => $factory->html('content')
            ->javascript()
            ->pdf()
            ->capture(),
    )->toThrow(RuntimeException::class, 'HTTP 401');
});

test('real Gotenberg crops selected elements to the maximum dimensions', function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('Cropping requires GD.');
    }

    $result = gotenbergScreenshot()->html(<<<'HTML'
        <style>
            body { margin: 0; }
            .card { margin: 30px; width: 200px; height: 100px; background: rgba(255, 0, 0, .5); }
        </style>
        <div class="card">Card</div>
        HTML)
        ->javascript()
        ->element('.card')
        ->png()
        ->maxSize(120, 70)
        ->capture();

    expect($result->width())->toBe(120);
    expect($result->height())->toBe(70);
});

test('real Gotenberg renders a URL reachable from the service', function () {
    $result = gotenbergScreenshot()->url('http://localhost:3000/health')
        ->javascript()
        ->size(400, 300)
        ->png()
        ->viewport()
        ->capture();

    expect($result->mimeType())->toBe('image/png');
    expect($result->width())->toBe(400);
    expect($result->height())->toBe(300);
});

test('real Gotenberg prints PDFs with custom dimensions', function () {
    $result = gotenbergScreenshot()->html('<h1>Invoice</h1>')
        ->javascript()
        ->pdf()
        ->paperSize(100, 200)
        ->margins(0, 0, 0, 0)
        ->landscape()
        ->capture();

    expect($result->contents())->toStartWith('%PDF-');
    expect($result->mimeType())->toBe('application/pdf');

    [$width, $height] = pdfPageSize($result->contents());

    expect($width)->toEqualWithDelta(200 / 25.4 * 72, 1);
    expect($height)->toEqualWithDelta(100 / 25.4 * 72, 1);
});

test('real Gotenberg honors CSS page sizes', function () {
    $result = gotenbergScreenshot()->html('<style>@page { size: 120mm 180mm; margin: 0 }</style><h1>CSS paper</h1>')
        ->javascript()
        ->pdf()
        ->preferCssPageSize()
        ->capture();

    [$width, $height] = pdfPageSize($result->contents());

    expect($width)->toEqualWithDelta(120 / 25.4 * 72, 1);
    expect($height)->toEqualWithDelta(180 / 25.4 * 72, 1);
});

test('real Gotenberg reports selectors that match no element', function () {
    expect(
        fn () => gotenbergScreenshot()->html('<p>Content</p>')
            ->javascript()
            ->element('#missing')
            ->capture(),
    )->toThrow(RuntimeException::class, 'HTTP 400');
});
