<?php

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use HeadlessChromium\Browser;
use HeadlessChromium\Clip;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Response;
use HeadlessChromium\Communication\Session;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\PageScreenshot;
use HeadlessChromium\PageUtils\ResponseWaiter;

function chromeDriver(Browser $browser): ChromeDriver
{
    return new class($browser) extends ChromeDriver
    {
        public function __construct(protected Browser $browser) {}

        protected function createBrowser(CaptureOptions $options): Browser
        {
            return $this->browser;
        }
    };
}

function chromePage(bool $shared = false): array
{
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    if ($shared) {
        $connection = Mockery::mock(Connection::class);
        $browser->shouldReceive('getConnection')->once()->andReturn($connection);
        $connection->shouldReceive('sendMessageSync')->once()
            ->withArgs(fn ($message) => $message->getMethod() === 'Target.createTarget')
            ->andReturn(new Response(['result' => ['targetId' => 'owned-target']], new Message('test')));
        $browser->shouldReceive('getPage')->once()->with('owned-target')->andReturn($page);
    } else {
        $browser->shouldReceive('createPage')->once()->andReturn($page);
    }
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));

    return [$browser, $page, $session];
}

test('the browser is closed even when page creation fails', function () {
    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('createPage')->once()->andThrow(new RuntimeException('page failure'));
    $browser->shouldReceive('close')->once()->andThrow(new RuntimeException('cleanup failure'));

    expect(fn () => chromeDriver($browser)->capture('hello', 'html', new CaptureOptions))
        ->toThrow(RuntimeException::class, 'page failure');
});

test('load timeouts stop loading and capture bounded content', function () {
    [$browser, $page, $session] = chromePage();
    $page->shouldReceive('setHtml')->once()->with('hello', 15000)->andThrow(new OperationTimedOut);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Page.stopLoading')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('getFullPageClip')->once()->with(60000)->andReturn(new Clip(0, 0, 5000, 20000));
    $image = Mockery::mock(PageScreenshot::class);
    $page->shouldReceive('screenshot')->once()->withArgs(fn ($options) => $options['clip']->getWidth() === 2048
        && $options['clip']->getHeight() === 10000 && ! isset($options['quality']))->andReturn($image);
    $image->shouldReceive('getBase64')->once()->with(60000)->andReturn(imageFixture());
    $page->shouldReceive('close')->once();
    $browser->shouldReceive('close')->once();

    expect(chromeDriver($browser)->capture('hello', 'html', new CaptureOptions)->width())->toBe(1);
});

test('strict load timeouts propagate and close all resources', function () {
    [$browser, $page] = chromePage();
    $page->shouldReceive('setHtml')->once()->andThrow(new OperationTimedOut('load failure'));
    $page->shouldReceive('close')->once();
    $browser->shouldReceive('close')->once();

    expect(fn () => chromeDriver($browser)->capture('hello', 'html', new CaptureOptions(failOnTimeout: true)))
        ->toThrow(OperationTimedOut::class, 'load failure');
});

test('failed page cleanup falls back to closing its target', function () {
    [$browser, $page, $session] = chromePage();
    $page->shouldReceive('setHtml')->once()->andThrow(new RuntimeException('capture failure'));
    $page->shouldReceive('close')->once()->andThrow(new RuntimeException('page cleanup failure'));
    $session->shouldReceive('getTargetId')->once()->andReturn('target-1');
    $connection = Mockery::mock(Connection::class);
    $browser->shouldReceive('getConnection')->once()->andReturn($connection);
    $connection->shouldReceive('sendMessageSync')->once()->withArgs(fn ($message) => $message->getMethod() === 'Target.closeTarget');
    $browser->shouldReceive('close')->once();

    expect(fn () => chromeDriver($browser)->capture('hello', 'html', new CaptureOptions))
        ->toThrow(RuntimeException::class, 'capture failure');
});

test('capture failures still close the page and browser', function () {
    [$browser, $page] = chromePage();
    $page->shouldReceive('setHtml')->once();
    $page->shouldReceive('getFullPageClip')->once()->andReturn(new Clip(0, 0, 800, 600));
    $page->shouldReceive('screenshot')->once()->andThrow(new RuntimeException('screenshot failure'));
    $page->shouldReceive('close')->once();
    $browser->shouldReceive('close')->once();

    expect(fn () => chromeDriver($browser)->capture('hello', 'html', new CaptureOptions))
        ->toThrow(RuntimeException::class, 'screenshot failure');
});

test('a shared capture failure closes its page without closing other workers browsers', function () {
    [$browser, $page] = chromePage(shared: true);
    $viewport = Mockery::mock(ResponseWaiter::class);
    $page->shouldReceive('setViewport')->once()->with(1024, 768)->andReturn($viewport);
    $viewport->shouldReceive('await')->once()->with(60000);
    $page->shouldReceive('setHtml')->once()->andThrow(new RuntimeException('capture failure'));
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');
    $driver = new class extends ChromeDriver
    {
        public function shared(Browser $browser): void
        {
            $this->capturePage($browser, 'hello', 'html', new CaptureOptions, shared: true);
        }
    };

    expect(fn () => $driver->shared($browser))->toThrow(RuntimeException::class, 'capture failure');
});

test('shared page initialization failures close only the owned target and preserve the original error', function ($cleanupFails) {
    $browser = Mockery::mock(Browser::class);
    $connection = Mockery::mock(Connection::class);
    $browser->shouldReceive('getConnection')->once()->andReturn($connection);
    $browser->shouldNotReceive('close');
    $connection->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Target.createTarget')
        ->andReturn(new Response(['result' => ['targetId' => 'owned-target']], new Message('test')));
    $browser->shouldReceive('getPage')->once()->with('owned-target')->andThrow(new OperationTimedOut('initialization failure'));
    $cleanup = $connection->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Target.closeTarget' && $message->getParams() === ['targetId' => 'owned-target']);

    if ($cleanupFails) {
        $cleanup->andThrow(new RuntimeException('cleanup failure'));
    } else {
        $cleanup->andReturn(new Response([], new Message('test')));
    }

    $driver = new class extends ChromeDriver
    {
        public function shared(Browser $browser): void
        {
            $this->capturePage($browser, 'hello', 'html', new CaptureOptions, shared: true);
        }
    };

    expect(fn () => $driver->shared($browser))->toThrow(OperationTimedOut::class, 'initialization failure');
})->with([false, true]);
