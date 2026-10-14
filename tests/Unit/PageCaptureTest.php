<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Drivers\Chrome\CapturesPage;
use DirectoryTree\Screenshot\Drivers\Chrome\PageCapture;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfOptions;
use HeadlessChromium\Browser;
use HeadlessChromium\Clip;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Response;
use HeadlessChromium\Communication\Session;
use HeadlessChromium\Communication\Target;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\PagePdf;
use HeadlessChromium\PageUtils\PageScreenshot;
use HeadlessChromium\PageUtils\ResponseWaiter;

test('load timeouts stop loading and capture bounded content', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->with('hello', 15000)->andThrow(new OperationTimedOut);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Page.stopLoading')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('getFullPageClip')->once()->with(60000)->andReturn(new Clip(0, 0, 5000, 20000));
    $image = Mockery::mock(PageScreenshot::class);
    $page->shouldReceive('screenshot')->once()->withArgs(fn ($options) => $options['clip']->getWidth() === 2048
        && $options['clip']->getHeight() === 10000 && ! isset($options['quality']))->andReturn($image);
    $image->shouldReceive('getBase64')->once()->with(60000)->andReturn(imageFixture());
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');

    expect((new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))->capture('hello', SourceType::Html)->width())->toBe(1);
});

test('strict load timeouts propagate and close the page', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->andThrow(new OperationTimedOut('load failure'));
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');

    expect(fn () => (new PageCapture($browser, new CaptureOptions(BrowserOptions::make()->setFailOnTimeout(true), ImageOptions::make())))->capture('hello', SourceType::Html))
        ->toThrow(OperationTimedOut::class, 'load failure');
});

test('failed page cleanup falls back to closing its target', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->andThrow(new RuntimeException('capture failure'));
    $page->shouldReceive('close')->once()->andThrow(new RuntimeException('page cleanup failure'));
    $session->shouldReceive('getTargetId')->once()->andReturn('target-1');
    $connection = Mockery::mock(Connection::class);
    $browser->shouldReceive('getConnection')->once()->andReturn($connection);
    $connection->shouldReceive('sendMessageSync')->once()->withArgs(fn ($message) => $message->getMethod() === 'Target.closeTarget');
    $browser->shouldNotReceive('close');

    expect(fn () => (new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))->capture('hello', SourceType::Html))
        ->toThrow(RuntimeException::class, 'capture failure');
});

test('capture failures still close the page', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once();
    $page->shouldReceive('getFullPageClip')->once()->andReturn(new Clip(0, 0, 800, 600));
    $page->shouldReceive('screenshot')->once()->andThrow(new RuntimeException('screenshot failure'));
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');

    expect(fn () => (new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))->capture('hello', SourceType::Html))
        ->toThrow(RuntimeException::class, 'screenshot failure');
});

test('a shared capture failure closes its page without closing other workers browsers', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $connection = Mockery::mock(Connection::class);
    $browser->shouldReceive('getConnection')->once()->andReturn($connection);
    $connection->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Target.createTarget')
        ->andReturn(new Response(['result' => ['targetId' => 'owned-target']], new Message('test')));
    $browser->shouldReceive('getPage')->once()->with('owned-target')->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $viewport = Mockery::mock(ResponseWaiter::class);
    $page->shouldReceive('setViewport')->once()->with(1024, 768)->andReturn($viewport);
    $viewport->shouldReceive('await')->once()->with(60000);
    $page->shouldReceive('setHtml')->once()->andThrow(new RuntimeException('capture failure'));
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');
    $capture = new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make()), shared: true);

    expect(fn () => $capture->capture('hello', SourceType::Html))->toThrow(RuntimeException::class, 'capture failure');
});

test('shared page initialization failures close only the owned target and preserve the original error', function ($cleanupFails) {
    $browser = Mockery::mock(Browser::class);
    $connection = Mockery::mock(Connection::class);
    $browser->shouldReceive('getConnection')->andReturn($connection);
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
        $connection->shouldReceive('getSendSyncDefaultTimeout')->once()->andReturn(60000);
        $target = Mockery::mock(Target::class);
        $browser->shouldReceive('getTarget')->with('owned-target')->twice()->andReturn($target, null);
        $connection->shouldReceive('readData')->once();
    }

    $capture = new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make()), shared: true);

    expect(fn () => $capture->capture('hello', SourceType::Html))->toThrow(OperationTimedOut::class, 'initialization failure');
})->with([false, true]);

test('PDF rendering receives print options and timeout and closes the page', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->with('invoice', 15000);
    $pdf = Mockery::mock(PagePdf::class);
    $page->shouldReceive('pdf')->once()->withArgs(fn ($options) => $options['landscape'] && $options['printBackground'])
        ->andReturn($pdf);
    $pdf->shouldReceive('getBase64')->once()->with(12345)->andReturn(base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf')));
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');

    $result = (new PageCapture($browser, new CaptureOptions(BrowserOptions::make()->setTimeouts(load: 15000, browser: 12345), PdfOptions::make()->setLandscape(true))))->capture('invoice', SourceType::Html);
    expect($result->mimeType())->toBe('application/pdf');
});

test('PDF printing failures preserve the error during page cleanup', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once();
    $page->shouldReceive('pdf')->once()->andThrow(new RuntimeException('print failure'));
    $page->shouldReceive('close')->once()->andThrow(new RuntimeException('cleanup failure'));
    $page->shouldReceive('getSession')->andThrow(new RuntimeException('closed session'));
    $browser->shouldNotReceive('close');

    expect(fn () => (new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), PdfOptions::make())))->capture('invoice', SourceType::Html))
        ->toThrow(RuntimeException::class, 'print failure');
});

test('the output enum selects Chrome image format and JPEG quality', function (OutputFormat $format) {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->with('image', 15000);
    $page->shouldReceive('getFullPageClip')->once()->with(60000)->andReturn(new Clip(0, 0, 800, 600));
    $image = Mockery::mock(PageScreenshot::class);
    $page->shouldReceive('screenshot')->once()->withArgs(fn ($parameters) => $parameters['format'] === $format->value
        && ($format === OutputFormat::Jpeg ? $parameters['quality'] === 91 : ! isset($parameters['quality'])))->andReturn($image);
    $page->shouldNotReceive('pdf');
    $image->shouldReceive('getBase64')->once()->with(60000)->andReturn(imageFixture());
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');

    expect((new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make()->setFormat($format, 91))))->capture('image', SourceType::Html))
        ->toBeInstanceOf(ImageResult::class);
})->with([OutputFormat::Png, OutputFormat::Jpeg]);

test('strategy resolution failures still close the page', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->with('hello', 15000);
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');

    $capture = new class($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())) extends PageCapture
    {
        protected function resolveCapture(): CapturesPage
        {
            throw new RuntimeException('strategy failure');
        }
    };

    expect(fn () => $capture->capture('hello', SourceType::Html))
        ->toThrow(RuntimeException::class, 'strategy failure');
});

test('page configuration failures release resources before loading or rendering', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->once()->andReturn($session);
    $session->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->andReturn(new Response(['error' => ['message' => 'configuration rejected']], new Message('test')));
    $page->shouldNotReceive('setHtml');
    $page->shouldNotReceive('screenshot');
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');

    expect(fn () => (new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))->capture('hello', SourceType::Html))
        ->toThrow(RuntimeException::class, 'Unable to configure JavaScript execution: configuration rejected');
});

test('failed stop-loading commands release resources without rendering partial content', function () {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $browser->shouldReceive('createPage')->once()->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->once()->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setHtml')->once()->andThrow(new OperationTimedOut('load failure'));
    $session->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Page.stopLoading')
        ->andReturn(new Response(['error' => ['message' => 'stop rejected']], new Message('test')));
    $page->shouldNotReceive('screenshot');
    $page->shouldReceive('close')->once();
    $browser->shouldNotReceive('close');

    expect(fn () => (new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make())))->capture('hello', SourceType::Html))
        ->toThrow(RuntimeException::class, 'Unable to stop page loading: stop rejected');
});

test('shared page cleanup reports its failure without replacing a capture error', function ($captureFails) {
    $browser = Mockery::mock(Browser::class);
    $page = Mockery::mock(Page::class);
    $session = Mockery::mock(Session::class);
    $connection = Mockery::mock(Connection::class);
    $viewport = Mockery::mock(ResponseWaiter::class);
    $browser->shouldNotReceive('close');
    $browser->shouldReceive('getConnection')->twice()->andReturn($connection);
    $connection->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Target.createTarget')
        ->andReturn(new Response(['result' => ['targetId' => 'owned-target']], new Message('test')));
    $browser->shouldReceive('getPage')->once()->with('owned-target')->andReturn($page);
    $page->shouldReceive('getSession')->andReturn($session);
    $session->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Emulation.setScriptExecutionDisabled')
        ->andReturn(new Response([], new Message('test')));
    $page->shouldReceive('setViewport')->once()->with(1024, 768)->andReturn($viewport);
    $viewport->shouldReceive('await')->once()->with(60000);
    $load = $page->shouldReceive('setHtml')->once();

    if ($captureFails) {
        $load->andThrow(new RuntimeException('capture failure'));
        $page->shouldNotReceive('screenshot');
    } else {
        $image = Mockery::mock(PageScreenshot::class);
        $page->shouldReceive('getFullPageClip')->once()->andReturn(new Clip(0, 0, 800, 600));
        $page->shouldReceive('screenshot')->once()->andReturn($image);
        $image->shouldReceive('getBase64')->once()->andReturn(imageFixture());
    }

    $page->shouldReceive('close')->once()->andThrow(new RuntimeException('page cleanup failure'));
    $session->shouldReceive('getTargetId')->once()->andReturn('owned-target');
    $connection->shouldReceive('sendMessageSync')->once()
        ->withArgs(fn ($message) => $message->getMethod() === 'Target.closeTarget' && $message->getParams() === ['targetId' => 'owned-target'])
        ->andReturn(new Response(['error' => ['message' => 'target rejected']], new Message('test')));
    $capture = new PageCapture($browser, new CaptureOptions(BrowserOptions::make(), ImageOptions::make()), shared: true);

    expect(fn () => $capture->capture('hello', SourceType::Html))->toThrow(
        RuntimeException::class,
        $captureFails ? 'capture failure' : 'Unable to close the screenshot page: target rejected',
    );
})->with([false, true]);
