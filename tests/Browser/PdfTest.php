<?php

use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\PendingPdf;
use DirectoryTree\Screenshot\Screenshot;
use HeadlessChromium\Exception\OperationTimedOut;
use Symfony\Component\Process\Process;

uses()->group('browser');

function pdfScreenshot(): Screenshot
{
    return new Screenshot(new ChromeDriver(getenv('CHROME_PATH') ?: null, [
        'noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1',
    ]));
}

function pdfPageSize(string $contents): array
{
    preg_match('~/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]~', $contents, $matches);

    return [(float) ($matches[1] ?? 0), (float) ($matches[2] ?? 0)];
}

test('real Chrome prints a paginated PDF independently of screenshot crop settings', function () {
    $result = pdfScreenshot()->html(file_get_contents(__DIR__.'/../Fixtures/document-pdf.html'))
        ->size(320, 240)->maxSize(100, 100)->viewport()->jpeg()
        ->pdf()->capture();

    expect($result->mimeType())->toBe('application/pdf')
        ->and($result->contents())->toStartWith('%PDF-')
        ->and(preg_match_all('~/Type\s*/Page\b~', $result->contents()))->toBe(2);
    [$width, $height] = pdfPageSize($result->contents());
    expect($width)->toEqualWithDelta(595.28, 1)->and($height)->toEqualWithDelta(841.89, 1);

    if ($path = getenv('SCREENSHOT_PDF_ARTIFACT_PATH')) {
        $result->save($path);
    }
});

test('PDF paper size orientation and CSS page rules control printed dimensions', function () {
    $result = pdfScreenshot()->html('<h1>Custom paper</h1>')->pdf()
        ->paperSize(100, 150)->landscape()->margins(0, 0, 0, 0)->background(false)->capture();
    [$width, $height] = pdfPageSize($result->contents());
    expect($width)->toEqualWithDelta(150 / 25.4 * 72, 1)
        ->and($height)->toEqualWithDelta(100 / 25.4 * 72, 1);

    $css = pdfScreenshot()->html('<style>@page { size: 120mm 180mm; margin: 0 }</style><h1>CSS paper</h1>')
        ->pdf()->preferCssPageSize()->capture();
    [$width, $height] = pdfPageSize($css->contents());
    expect($width)->toEqualWithDelta(120 / 25.4 * 72, 1)
        ->and($height)->toEqualWithDelta(180 / 25.4 * 72, 1);
});

test('PDF URL captures honor strict and partial page load timeouts', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = new Process([PHP_BINARY, '-S', $address, __DIR__.'/../Fixtures/server.php']);
    $server->start();

    try {
        $server->waitUntil(fn ($type, $output) => str_contains($output, 'Development Server'));
        $factory = pdfScreenshot();
        expect($factory->url('http://'.$address)->pdf()->capture()->mimeType())->toBe('application/pdf');
        $html = '<h1>Partial PDF</h1><img src="http://'.$address.'/slow">';
        expect($factory->html($html)->pdf()->timeouts(load: 100)->capture()->mimeType())->toBe('application/pdf');
        expect(fn () => $factory->html($html)->pdf()->timeouts(load: 100)->failOnTimeout()->capture())
            ->toThrow(OperationTimedOut::class);
    } finally {
        $server->stop();
    }
});

test('image and PDF builders capture independently using the same terminal methods', function () {
    $image = pdfScreenshot()->html('<h1>Any format</h1>')->size(320, 240)->viewport();
    $pdf = $image->pdf()->paper(PaperSize::Letter);

    expect($pdf->capture()->mimeType())->toBe('application/pdf')
        ->and($image->png()->capture()->mimeType())->toBe('image/png')
        ->and($image->jpeg()->capture()->mimeType())->toBe('image/jpeg');

    $result = $pdf->capture();
    [$width, $height] = pdfPageSize($result->contents());
    expect($width)->toEqualWithDelta(612, 1)->and($height)->toEqualWithDelta(792, 1);
});

test('real PDFs use format defaults and explicit paper overrides', function () {
    $factory = pdfScreenshot()->configure(OutputFormat::Pdf,
        fn (PendingPdf $pdf) => $pdf->paperSize(100, 150)->landscape());
    $default = $factory->html('<h1>Defaults</h1>')->pdf()->capture();
    [$width, $height] = pdfPageSize($default->contents());
    expect($width)->toEqualWithDelta(150 / 25.4 * 72, 1)->and($height)->toEqualWithDelta(100 / 25.4 * 72, 1);

    $override = $factory->html('<h1>Override</h1>')->pdf()->paper(PaperSize::Letter)->landscape(false)->capture();
    [$width, $height] = pdfPageSize($override->contents());
    expect($width)->toEqualWithDelta(612, 1)->and($height)->toEqualWithDelta(792, 1);
});
