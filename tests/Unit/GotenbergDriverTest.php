<?php

use DirectoryTree\Screenshot\Drivers\GotenbergDriver;
use DirectoryTree\Screenshot\Screenshot;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

test('Gotenberg sends HTML as an in-memory multipart file and preserves PDF layout', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], file_get_contents(__DIR__.'/../../resources/blank.pdf')),
    ]);
    $stack = HandlerStack::create($mock);

    $stack->push(Middleware::history($history));

    $factory = new Screenshot(
        new GotenbergDriver(
            url: 'https://gotenberg.example.test/api/',
            http: new Client([
                'handler' => $stack,
                'auth' => ['user', 'password'],
            ]),
        ),
    );

    $result = $factory->html('<h1>Invoice</h1>')
        ->javascript()
        ->pdf()
        ->paperSize(100, 200)
        ->landscape()
        ->margins(1, 2, 3, 4)
        ->background(false)
        ->preferCssPageSize()
        ->capture();

    expect($result->mimeType())->toBe('application/pdf');

    $request = $history[0]['request'];
    $body = (string) $request->getBody();

    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toBe('https://gotenberg.example.test/api/forms/chromium/convert/html');
    expect($request->getHeaderLine('Authorization'))->toBe('Basic '.base64_encode('user:password'));
    expect($request->getHeaderLine('Content-Type'))->toStartWith('multipart/form-data; boundary=');
    expect($body)->toContain('name="files"; filename="index.html"', '<h1>Invoice</h1>');

    $fields = [
        'paperWidth' => '100mm',
        'paperHeight' => '200mm',
        'marginTop' => '1mm',
        'marginRight' => '2mm',
        'marginBottom' => '3mm',
        'marginLeft' => '4mm',
        'landscape' => 'true',
        'printBackground' => 'false',
        'preferCssPageSize' => 'true',
    ];

    foreach ($fields as $field => $value) {
        expect($body)->toMatch('/name="'.preg_quote($field, '/').'"\r\n(?:[^\r\n]+\r\n)*\r\n'.preg_quote($value, '/').'\r\n/');
    }
});

test('Gotenberg selects the URL screenshot route with the original viewport', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], base64_decode(imageFixture())),
    ]);
    $stack = HandlerStack::create($mock);

    $stack->push(Middleware::history($history));

    $factory = new Screenshot(
        new GotenbergDriver(http: new Client(['handler' => $stack])),
    );

    $result = $factory->url('https://example.com/report')
        ->javascript()
        ->size(640, 480)
        ->png()
        ->viewport()
        ->maxSize(200, 100)
        ->capture();

    expect($result->mimeType())->toBe('image/png');
    expect($result->width())->toBe(1);
    expect((string) $history[0]['request']->getUri())->toEndWith('/forms/chromium/screenshot/url');

    $body = (string) $history[0]['request']->getBody();

    expect($body)->toContain('https://example.com/report', 'name="width"', "\r\n640\r\n", "\r\n480\r\n", "\r\ntrue\r\n");
    expect($body)->not->toContain('filename=', 'name="quality"', 'name="selector"');
});

test('Gotenberg selects the URL PDF route', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], file_get_contents(__DIR__.'/../../resources/blank.pdf')),
    ]);
    $stack = HandlerStack::create($mock);

    $stack->push(Middleware::history($history));

    $factory = new Screenshot(
        new GotenbergDriver(http: new Client(['handler' => $stack])),
    );

    $factory->url('https://example.com/report')
        ->javascript()
        ->pdf()
        ->capture();

    expect((string) $history[0]['request']->getUri())->toEndWith('/forms/chromium/convert/url');
});

test('Gotenberg forwards image selectors and rejects unexpected output formats', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], base64_decode(imageFixture())),
    ]);
    $stack = HandlerStack::create($mock);

    $stack->push(Middleware::history($history));

    $factory = new Screenshot(
        new GotenbergDriver(http: new Client(['handler' => $stack])),
    );

    expect(
        fn () => $factory->html('<div id="card">Card</div>')
            ->javascript()
            ->element('#card')
            ->jpeg(73)
            ->capture(),
    )->toThrow(RuntimeException::class, 'unexpected image format');

    $body = (string) $history[0]['request']->getBody();

    expect($body)->toContain('name="selector"', "\r\n#card\r\n", "\r\njpeg\r\n", "\r\n73\r\n");
});

test('Gotenberg uses the browser timeout for the entire request', function () {
    $history = [];
    $mock = new MockHandler([
        new Response(200, [], base64_decode(imageFixture())),
    ]);
    $stack = HandlerStack::create($mock);

    $stack->push(Middleware::history($history));

    $factory = new Screenshot(
        new GotenbergDriver(http: new Client(['handler' => $stack])),
    );

    $factory->html('content')
        ->javascript()
        ->timeouts(browser: 1250)
        ->capture();

    expect($history[0]['options']['timeout'])->toBe(1.25);
    expect($history[0]['options']['http_errors'])->toBeFalse();
    expect($history[0]['options']['allow_redirects'])->toBeFalse();
});

test('Gotenberg reports HTTP errors and the remote trace', function (int $status) {
    $mock = new MockHandler([
        new Response($status, ['Gotenberg-Trace' => 'trace-id'], 'conversion failed'),
    ]);

    $factory = new Screenshot(
        new GotenbergDriver(http: new Client(['handler' => $mock])),
    );

    expect(
        fn () => $factory->html('content')
            ->javascript()
            ->capture(),
    )->toThrow(RuntimeException::class, "Gotenberg capture failed (HTTP {$status}, trace: trace-id): conversion failed");
})->with([302, 400, 401, 503]);

test('Gotenberg preserves connection and timeout errors', function () {
    $mock = new MockHandler([
        new ConnectException(
            'Connection timed out',
            new Request('POST', 'https://gotenberg.example.test'),
        ),
    ]);

    $factory = new Screenshot(
        new GotenbergDriver(http: new Client(['handler' => $mock])),
    );

    expect(
        fn () => $factory->html('content')
            ->javascript()
            ->capture(),
    )->toThrow(ConnectException::class, 'Connection timed out');
});

test('Gotenberg rejects unsupported settings before sending requests', function (Closure $capture, string $message) {
    $history = [];
    $mock = new MockHandler;
    $stack = HandlerStack::create($mock);

    $stack->push(Middleware::history($history));

    $factory = new Screenshot(
        new GotenbergDriver(http: new Client(['handler' => $stack])),
    );

    expect(fn () => $capture($factory))->toThrow(InvalidArgumentException::class, $message);
    expect($history)->toBe([]);
})->with([
    'JavaScript mismatch' => [fn ($factory) => $factory->html('content')->capture(), 'JavaScript setting must match'],
    'sockets' => [
        fn ($factory) => $factory->html('content')
            ->javascript()
            ->socket('shared')
            ->capture(),
        'shared socket keys',
    ],
    'PDF elements' => [
        fn ($factory) => $factory->html('content')
            ->javascript()
            ->element('#card')
            ->pdf()
            ->capture(),
        'element selection for PDF',
    ],
]);

test('Gotenberg supports services configured with JavaScript disabled', function () {
    $mock = new MockHandler([
        new Response(200, [], base64_decode(imageFixture())),
    ]);

    $driver = new GotenbergDriver(
        javascript: false,
        http: new Client(['handler' => $mock]),
    );
    $factory = new Screenshot($driver);

    expect($factory->html('content')->capture()->width())->toBe(1);
});

test('Gotenberg validates successful response bodies', function (string $body, bool $pdf) {
    $mock = new MockHandler([
        new Response(200, [], $body),
    ]);

    $factory = new Screenshot(
        new GotenbergDriver(http: new Client(['handler' => $mock])),
    );

    $source = $factory->html('content')->javascript();

    expect(fn () => ($pdf ? $source->pdf() : $source->png())->capture())
        ->toThrow(InvalidArgumentException::class);
})->with([
    ['not an image', false],
    ['not a PDF', true],
]);

test('Gotenberg rejects invalid base URLs', function (string $url) {
    expect(fn () => new GotenbergDriver($url))->toThrow(InvalidArgumentException::class);
})->with(['', 'file:///tmp', 'localhost:3000', 'https://example.test?token=secret', 'https://example.test/#fragment']);
