<?php

use DirectoryTree\Screenshot\Contracts\CaptureContext;
use DirectoryTree\Screenshot\Drivers\Chrome\ChromeCaptureContext;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Drivers\Gotenberg\GotenbergCaptureContext;
use DirectoryTree\Screenshot\Drivers\Gotenberg\Request;
use DirectoryTree\Screenshot\Drivers\GotenbergDriver;
use DirectoryTree\Screenshot\Screenshot;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HeadlessChromium\Page;

test('Gotenberg callbacks customize the prepared request in registration order', function (string $format) {
    $image = imagecreate(1, 1);
    ob_start();
    imagejpeg($image);
    $jpeg = ob_get_clean();
    imagedestroy($image);
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, [], $format === 'pdf'
            ? file_get_contents(__DIR__.'/../../resources/blank.pdf')
            : ($format === 'jpeg' ? $jpeg : base64_decode(imageFixture()))),
    ]));
    $stack->push(Middleware::history($history));
    $driver = new GotenbergDriver(http: new Client(['handler' => $stack]));
    $calls = [];

    $source = (new Screenshot($driver))->html('<h1>Hello</h1>')->javascript()
        ->before(function (CaptureContext $context) use ($driver, &$calls) {
            expect($context->driver())->toBe($driver);
            expect($context->request()->options['multipart'][0]['contents'])->toBe('<h1>Hello</h1>');
            $context->request()->set('waitForExpression', 'window.ready')->set('skipNetworkIdleEvent', true);
            $calls[] = 'source';
        });

    $source->{$format}()->before(function (CaptureContext $context) use (&$calls) {
        $context->request()->set('waitForExpression', 'window.finished');
        $context->request()->options['headers']['X-Capture'] = 'custom';
        $calls[] = 'output';
    })->capture();

    $body = (string) $history[0]['request']->getBody();
    expect($calls)->toBe(['source', 'output']);
    expect($body)->toContain('window.finished', 'true')->not->toContain('window.ready');
    expect($history[0]['request']->getHeaderLine('X-Capture'))->toBe('custom');
})->with(['png', 'jpeg', 'pdf']);

test('callbacks on an output do not leak into its source or sibling outputs', function () {
    $stack = HandlerStack::create(new MockHandler(array_fill(0, 3, new Response(200, [], base64_decode(imageFixture())))));
    $source = (new Screenshot(new GotenbergDriver(http: new Client(['handler' => $stack]))))
        ->html('hello')->javascript();
    $calls = 0;
    $image = $source->png()->before(function () use (&$calls) {
        $calls++;
    });

    $source->capture();
    $source->png()->capture();
    $image->capture();

    expect($calls)->toBe(1);
});

test('each driver context implements the shared contract and exposes its resource', function () {
    $driver = new ChromeDriver;
    $page = Mockery::mock(Page::class);
    $context = new ChromeCaptureContext($driver, $page);

    expect($context)->toBeInstanceOf(CaptureContext::class);
    expect($context->driver())->toBe($driver);
    expect($context->page())->toBe($page);

    $driver = new GotenbergDriver;
    $request = new Request('http://localhost:3000', ['multipart' => []]);
    $context = new GotenbergCaptureContext($driver, $request);

    expect($context)->toBeInstanceOf(CaptureContext::class);
    expect($context->driver())->toBe($driver);
    expect($context->request())->toBe($request);
});

test('Gotenberg callback failures propagate without sending the request', function () {
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldNotReceive('request');

    expect(fn () => (new Screenshot(new GotenbergDriver(http: $http)))
        ->html('hello')->javascript()
        ->before(function (CaptureContext $context) {
            throw new RuntimeException('preparation failed');
        })->capture())->toThrow(RuntimeException::class, 'preparation failed');
});
