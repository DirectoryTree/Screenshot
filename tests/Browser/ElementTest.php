<?php

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Drivers\Chrome\PdfCapture;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\Screenshot;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\DomException;
use HeadlessChromium\Exception\JavascriptException;
use Symfony\Component\Process\Process;

uses()->group('browser');

test('element images include borders and padding beyond the viewport', function ($format, $fullPage) {
    $source = (new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    )))->html(<<<'HTML'
        <style>
            body { margin: 0; }
            .card { margin: 900px 0 0 90px; width: 200px; height: 100px; padding: 10px; border: 5px solid blue; background: green; }
        </style>
        <div class="card" data-label='a"b'>Selected card</div>
        <div class="card" style="width: 400px">Other card</div>
        <script>document.querySelector('.card').style.width = '600px';</script>
        HTML)->size(400, 300)->element('.card[data-label=\'a"b\']');

    $image = ($format === OutputFormat::Png ? $source->png() : $source->jpeg())
        ->fullPage($fullPage)
        ->capture();

    expect($image->width())->toBe(230);
    expect($image->height())->toBe(130);
    expect($image->mimeType())->toBe($format === OutputFormat::Png ? 'image/png' : 'image/jpeg');

    $bounded = $source->png()->maxSize(120, 80)->capture();

    expect($bounded->width())->toBe(120);
    expect($bounded->height())->toBe(80);
})->with([OutputFormat::Png, OutputFormat::Jpeg])->with([true, false]);

test('element selection uses the first match and the current JavaScript layout', function () {
    $result = (new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    )))->html(<<<'HTML'
        <div class="card" style="width: 200px; height: 100px"></div>
        <div class="card" style="width: 500px; height: 300px"></div>
        <script>document.querySelector('.card').style.width = '250px';</script>
        HTML)->javascript()->element('.card')->capture();

    expect($result->width())->toBe(250);
    expect($result->height())->toBe(100);
});

test('missing elements fail clearly for images and PDFs', function ($pdf) {
    $factory = new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    ));
    $source = $factory->html('<div>Content</div>')->element('#missing');

    expect(fn () => ($pdf ? $source->pdf() : $source->png())->capture())
        ->toThrow(RuntimeException::class, 'No element matches the selector [#missing].');
})->with(['image' => false, 'PDF' => true]);

test('image elements need layout bounds', function ($html, $exception, $message) {
    $factory = new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    ));

    expect(fn () => $factory->html($html)->element('#invoice')->capture())
        ->toThrow($exception, $message);
})->with([
    'hidden' => ['<div id="invoice" style="display:none">Hidden</div>', DomException::class, 'Could not compute box model'],
    'empty' => ['<div id="invoice"></div>', RuntimeException::class, 'The element [#invoice] has no layout bounds.'],
]);

test('invalid CSS selectors propagate the browser error', function ($pdf, $exception) {
    $factory = new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    ));
    $source = $factory->html('<div>Content</div>')->element('[');

    expect(fn () => ($pdf ? $source->pdf() : $source->png())->capture())->toThrow($exception);
})->with([
    'image' => [false, DomException::class],
    'PDF' => [true, JavascriptException::class],
]);

test('element PDFs paginate selected content using the configured paper and margins', function () {
    $source = (new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    )))->html(<<<'HTML'
        <style>
            body { margin: 0; }
            .shell { display: flex; height: 3000px; padding: 100px; }
            #invoice { width: 200px; }
            #invoice section { height: 100px; }
            #invoice section + section { break-before: page; }
        </style>
        <header style="height: 3000px">Unrelated header</header>
        <main class="shell"><aside>Unrelated sidebar</aside><article id="invoice">
            <section>Invoice details</section><section>Payment details</section>
        </article></main>
        <footer style="height: 3000px">Unrelated footer</footer>
        HTML)->element('#invoice');

    $pdf = $source->pdf()->paperSize(100, 150)->margins(5, 5, 5, 5)->capture();

    expect(preg_match_all('~/Type\s*/Page\b~', $pdf->contents()))->toBe(2);
    expect($pdf->contents())->toContain('/Type /Font');
    expect($pdf->contents())->not->toContain('/Subtype /Image');

    preg_match('~/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]~', $pdf->contents(), $matches);
    [$width, $height] = [(float) $matches[1], (float) $matches[2]];

    expect($width)->toEqualWithDelta(100 / 25.4 * 72, 1);
    expect($height)->toEqualWithDelta(150 / 25.4 * 72, 1);

    $image = $source->png()->capture();

    expect($image->width())->toBe(200);
    expect($image->height())->toBe(3000);
});

test('PDF isolation preserves document stylesheets and element state without correcting layout', function () {
    $browser = (new BrowserFactory(getenv('CHROME_PATH') ?: null))->createBrowser([
        'noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1',
    ]);

    try {
        $page = $browser->createPage();
        $page->getSession()->sendMessageSync(new Message('Emulation.setScriptExecutionDisabled', ['value' => true]));
        $page->setHtml(<<<'HTML'
            <html><head><style>
                body { color: rgb(0, 0, 255); }
                .shell { padding: 100px; height: 3000px; color: rgb(0, 128, 0); }
                .shell > #invoice { border: 3px solid; }
                #invoice { margin: 50px; width: 200px; }
                @media print { .screen-only { display: none; } }
            </style></head><body>
                <header>Unrelated header</header>
                <main class="shell"><article id="invoice">
                    <p>Invoice details</p><p class="screen-only">Screen only</p>
                    <input value="Original">
                </article></main>
                <style>#invoice p { font-weight: 700; }</style>
                <link rel="stylesheet" href="data:text/css,%23invoice%20%7B%20padding%3A%207px%3B%20%7D">
                <footer>Unrelated footer</footer>
                <script>document.querySelector('#invoice').remove();</script>
            </body></html>
            HTML);
        $page->evaluate("document.querySelector('input').value = 'Current value'")->getReturnValue();

        $capture = new PdfCapture(new CaptureOptions(new BrowserOptions, new PdfOptions, '#invoice'));

        $capture->capture($page);

        $page->getSession()->sendMessageSync(new Message('Emulation.setEmulatedMedia', ['media' => 'print']));
        $layout = $page->evaluate(<<<'JS'
            (() => {
                const element = document.querySelector('#invoice');
                return {
                    parent: element.parentElement.tagName,
                    color: getComputedStyle(element).color,
                    border: getComputedStyle(element).borderTopWidth,
                    margin: getComputedStyle(element).marginTop,
                    padding: getComputedStyle(element).paddingTop,
                    weight: getComputedStyle(element.querySelector('p')).fontWeight,
                    value: element.querySelector('input').value,
                    wrappers: document.querySelectorAll('.shell, header, footer').length,
                    stylesheets: document.head.querySelectorAll('style, link[rel="stylesheet"]').length,
                    text: document.body.innerText,
                };
            })()
            JS)->getReturnValue();

        expect($layout['parent'])->toBe('BODY');
        expect($layout['color'])->toBe('rgb(0, 0, 255)');
        expect($layout['border'])->toBe('0px');
        expect($layout['margin'])->toBe('50px');
        expect($layout['padding'])->toBe('7px');
        expect($layout['weight'])->toBe('700');
        expect($layout['value'])->toBe('Current value');
        expect($layout['wrappers'])->toBe(0);
        expect($layout['stylesheets'])->toBe(3);
        expect($layout['text'])->toContain('Invoice details');
        expect($layout['text'])->not->toContain('Unrelated', 'Screen only');
    } finally {
        $browser->close();
    }
});

test('PDF isolation accepts the body and document element', function ($selector) {
    $factory = new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    ));
    $result = $factory->html('<h1>Whole document</h1>')->element($selector)->pdf()->capture();

    expect($result->mimeType())->toBe('application/pdf');
    expect($result->contents())->toContain('/Type /Font');
})->with(['body', 'html']);

test('PDF element visibility follows print styles', function () {
    $factory = new Screenshot(new ChromeDriver(
        getenv('CHROME_PATH') ?: null,
        ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
    ));
    $source = $factory->html(<<<'HTML'
        <style>
            #invoice { display: none; }
            @media print { #invoice { display: block; } }
        </style>
        <article id="invoice"><h1>Print-only invoice</h1></article>
        HTML)->element('#invoice');

    expect($source->pdf()->capture()->mimeType())->toBe('application/pdf');
    expect(fn () => $source->png()->capture())
        ->toThrow(DomException::class, 'Could not compute box model');
});

test('URL sources can select elements for both image and PDF captures', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = new Process([PHP_BINARY, '-S', $address, __DIR__.'/../Fixtures/server.php']);
    $server->start();

    try {
        $server->waitUntil(fn ($type, $output) => str_contains($output, 'Development Server'));
        $source = (new Screenshot(new ChromeDriver(
            getenv('CHROME_PATH') ?: null,
            ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'],
        )))->url('http://'.$address)->element('.card');
        $image = $source->png()->capture();
        $pdf = $source->pdf()->capture();

        expect($image->width())->toBe(544);
        expect($image->height())->toBeLessThan(960);
        expect(preg_match_all('~/Type\s*/Page\b~', $pdf->contents()))->toBe(1);
    } finally {
        $server->stop();
    }
});
