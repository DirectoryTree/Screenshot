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

beforeEach(function () {
    $this->history = [];
    $this->mock = new MockHandler;
    $stack = HandlerStack::create($this->mock);

    $stack->push(Middleware::history($this->history));

    $this->factory = new Screenshot(
        new GotenbergDriver(
            url: 'https://gotenberg.example.test/api/',
            httpOptions: [
                'handler' => $stack,
                'auth' => ['user', 'password'],
            ],
        ),
    );
});

test('Gotenberg sends HTML as an in-memory multipart file and preserves PDF layout', function () {
    $this->mock->append(new Response(200, [], file_get_contents(__DIR__.'/../../resources/blank.pdf')));

    $result = $this->factory->html('<h1>Invoice</h1>')
        ->javascript()
        ->pdf()
        ->paperSize(100, 200)
        ->landscape()
        ->margins(1, 2, 3, 4)
        ->background(false)
        ->preferCssPageSize()
        ->capture();

    expect($result->mimeType())->toBe('application/pdf');

    $request = $this->history[0]['request'];
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
    $this->mock->append(new Response(200, [], base64_decode(imageFixture())));

    $result = $this->factory->url('https://example.com/report')
        ->javascript()
        ->size(640, 480)
        ->png()
        ->viewport()
        ->maxSize(200, 100)
        ->capture();

    expect($result->mimeType())->toBe('image/png');
    expect($result->width())->toBe(1);
    expect((string) $this->history[0]['request']->getUri())->toEndWith('/forms/chromium/screenshot/url');

    $body = (string) $this->history[0]['request']->getBody();

    expect($body)->toContain('https://example.com/report', 'name="width"', "\r\n640\r\n", "\r\n480\r\n", "\r\ntrue\r\n");
    expect($body)->not->toContain('filename=', 'name="quality"', 'name="selector"');
});

test('Gotenberg selects the URL PDF route', function () {
    $this->mock->append(new Response(200, [], file_get_contents(__DIR__.'/../../resources/blank.pdf')));

    $this->factory->url('https://example.com/report')
        ->javascript()
        ->pdf()
        ->capture();

    expect((string) $this->history[0]['request']->getUri())->toEndWith('/forms/chromium/convert/url');
});

test('Gotenberg forwards image selectors and rejects unexpected output formats', function () {
    $this->mock->append(new Response(200, [], base64_decode(imageFixture())));

    expect(
        fn () => $this->factory->html('<div id="card">Card</div>')
            ->javascript()
            ->element('#card')
            ->jpeg(73)
            ->capture(),
    )->toThrow(RuntimeException::class, 'unexpected image format');

    $body = (string) $this->history[0]['request']->getBody();

    expect($body)->toContain('name="selector"', "\r\n#card\r\n", "\r\njpeg\r\n", "\r\n73\r\n");
});

test('Gotenberg uses the browser timeout for the entire request', function () {
    $this->mock->append(new Response(200, [], base64_decode(imageFixture())));

    $this->factory->html('content')
        ->javascript()
        ->timeouts(browser: 1250)
        ->capture();

    expect($this->history[0]['options']['timeout'])->toBe(1.25);
    expect($this->history[0]['options']['http_errors'])->toBeFalse();
    expect($this->history[0]['options']['allow_redirects'])->toBeFalse();
});

test('Gotenberg reports HTTP errors and the remote trace', function (int $status) {
    $this->mock->append(new Response($status, ['Gotenberg-Trace' => 'trace-id'], 'conversion failed'));

    expect(
        fn () => $this->factory->html('content')
            ->javascript()
            ->capture(),
    )->toThrow(RuntimeException::class, "Gotenberg capture failed (HTTP {$status}, trace: trace-id): conversion failed");
})->with([302, 400, 401, 503]);

test('Gotenberg preserves connection and timeout errors', function () {
    $this->mock->append(new ConnectException(
        'Connection timed out',
        new Request('POST', 'https://gotenberg.example.test'),
    ));

    expect(
        fn () => $this->factory->html('content')
            ->javascript()
            ->capture(),
    )->toThrow(ConnectException::class, 'Connection timed out');
});

test('Gotenberg rejects unsupported settings before sending requests', function (Closure $capture, string $message) {
    expect(fn () => $capture($this->factory))->toThrow(InvalidArgumentException::class, $message);
    expect($this->history)->toBe([]);
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
    $this->mock->append(new Response(200, [], base64_decode(imageFixture())));
    $driver = new GotenbergDriver(
        javascript: false,
        http: new Client(['handler' => $this->mock]),
    );
    $factory = new Screenshot($driver);

    expect($factory->html('content')->capture()->width())->toBe(1);
});

test('Gotenberg validates successful response bodies', function (string $body, bool $pdf) {
    $this->mock->append(new Response(200, [], $body));
    $source = $this->factory->html('content')->javascript();

    expect(fn () => ($pdf ? $source->pdf() : $source->png())->capture())
        ->toThrow(InvalidArgumentException::class);
})->with([
    ['not an image', false],
    ['not a PDF', true],
]);

test('Gotenberg rejects invalid base URLs', function (string $url) {
    expect(fn () => new GotenbergDriver($url))->toThrow(InvalidArgumentException::class);
})->with(['', 'file:///tmp', 'localhost:3000', 'https://example.test?token=secret', 'https://example.test/#fragment']);
