<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use HeadlessChromium\Browser;
use HeadlessChromium\Clip;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Response;
use HeadlessChromium\Communication\Session;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\PageScreenshot;
use HeadlessChromium\PageUtils\ResponseWaiter;

test('dedicated browsers close even when page creation fails', function () {
    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('createPage')->once()->andThrow(new RuntimeException('page failure'));
    $browser->shouldReceive('close')->once()->andThrow(new RuntimeException('cleanup failure'));
    $driver = new class($browser) extends ChromeDriver
    {
        public function __construct(
            protected Browser $browser
        ) {}

        protected function createBrowser(BrowserOptions $options): Browser
        {
            return $this->browser;
        }
    };

    expect(fn () => $driver->capture('hello', SourceType::Html, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))
        ->toThrow(RuntimeException::class, 'page failure');
});

test('dedicated browsers close after a successful page capture', function ($cleanupFails) {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $image = Mockery::mock(PageScreenshot::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->once()->andReturn($session);
    $session->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->with('hello', 15000);
    $page->shouldReceive('getFullPageClip')->once()->with(60000)->andReturn(new Clip(0, 0, 800, 600));
    $page->shouldReceive('screenshot')->once()->andReturn($image);
    $image->shouldReceive('getBase64')->once()->with(60000)->andReturn(imageFixture());
    $page->shouldReceive('close')->once();
    $close = $browser->shouldReceive('close')->once();

    if ($cleanupFails) {
        $close->andThrow(new RuntimeException('browser cleanup failure'));
    }

    $driver = new class($browser) extends ChromeDriver
    {
        public function __construct(
            protected Browser $browser
        ) {}

        protected function createBrowser(BrowserOptions $options): Browser
        {
            return $this->browser;
        }
    };

    if ($cleanupFails) {
        expect(fn () => $driver->capture('hello', SourceType::Html, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))
            ->toThrow(RuntimeException::class, 'browser cleanup failure');
    } else {
        expect($driver->capture('hello', SourceType::Html, new CaptureOptions(BrowserOptions::make(), ImageOptions::make()))->width())->toBe(1);
    }
})->with([false, true]);

test('rendering errors survive failures in both page and browser cleanup', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $connection = Mockery::mock(Connection::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once();
    $page->shouldReceive('getFullPageClip')->once()->andReturn(new Clip(0, 0, 800, 600));
    $page->shouldReceive('screenshot')->once()->andThrow(new RuntimeException('rendering failure'));
    $page->shouldReceive('close')->once()->andThrow(new RuntimeException('page cleanup failure'));
    $session->shouldReceive('getTargetId')->once()->andReturn('owned-target');
    $browser->shouldReceive('getConnection')->once()->andReturn($connection);
    $connection->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Target.closeTarget')
        ->andThrow(new RuntimeException('target cleanup failure'));
    $browser->shouldReceive('close')->once()->andThrow(new RuntimeException('browser cleanup failure'));
    $driver = new class($browser) extends ChromeDriver
    {
        public function __construct(
            protected Browser $browser
        ) {}

        protected function createBrowser(BrowserOptions $options): Browser
        {
            return $this->browser;
        }
    };

    expect(fn () => $driver->capture('hello', SourceType::Html, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))
        ->toThrow(RuntimeException::class, 'rendering failure');
});

test('shared captures use a socket lease and connection settings without closing the browser', function () {
    $browser = Mockery::mock(Browser::class);
    $connection = Mockery::mock(Connection::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $viewport = Mockery::mock(ResponseWaiter::class);
    $image = Mockery::mock(PageScreenshot::class);
    $socket = Mockery::mock(BrowserSocket::class);
    $socket->shouldReceive('run')->once()->with(
        Mockery::type('callable'), Mockery::type('callable'),
        ['headers' => ['X-Test' => 'capture'], 'sendSyncDefaultTimeout' => 1234],
    )->andReturnUsing(fn ($create, $capture) => $capture($create()));
    $browser->shouldNotReceive('close');
    $browser->shouldNotReceive('createPage');
    $browser->shouldReceive('getConnection')->twice()->andReturn($connection);
    $connection->shouldReceive('setConnectionHttpHeaders')->once()->with(['X-Test' => 'capture']);
    $connection->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Target.createTarget')
        ->andReturn(new Response(['result' => ['targetId' => 'owned-target']], new Message('test')));
    $browser->shouldReceive('getPage')->once()->with('owned-target')->andReturn($page);
    $page->shouldReceive('setViewport')->once()->with(800, 600)->andReturn($viewport);
    $viewport->shouldReceive('await')->once()->with(1234);
    $page->shouldReceive('getSession')->once()->andReturn($session);
    $session->shouldReceive('sendMessageSync')->once()->withArgs(fn ($message, $timeout) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled' && $timeout === 1234
    )->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->with('hello', 2000);
    $page->shouldReceive('getFullPageClip')->once()->with(1234)->andReturn(new Clip(0, 0, 800, 600));
    $page->shouldReceive('screenshot')->once()->andReturn($image);
    $image->shouldReceive('getBase64')->once()->with(1234)->andReturn(imageFixture());
    $page->shouldReceive('close')->once();
    $driver = new class($browser, $socket) extends ChromeDriver
    {
        public function __construct(
            protected Browser $browser,
            protected BrowserSocket $browserSocket,
        ) {
            parent::__construct(browserOptions: ['headers' => ['X-Test' => 'capture'], 'sendSyncDefaultTimeout' => 999]);
        }

        protected function createBrowser(BrowserOptions $options): Browser
        {
            return $this->browser;
        }

        protected function socket(BrowserOptions $options): ?BrowserSocket
        {
            return $this->browserSocket;
        }
    };
    $options = new CaptureOptions(
        BrowserOptions::make()->setSocket('shared')->setSize(800, 600)->setTimeouts(2000, 1234),
        ImageOptions::make(),
    );

    expect($driver->capture('hello', SourceType::Html, $options)->width())->toBe(1);
});

test('source strings are rejected before starting a browser', function ($type) {
    expect(fn () => (new ChromeDriver)->capture('invoice', $type, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))
        ->toThrow(TypeError::class);
})->with(['html', 'url', 'invalid']);
