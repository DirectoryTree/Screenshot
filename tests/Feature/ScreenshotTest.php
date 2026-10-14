<?php

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;
use DirectoryTree\Screenshot\PendingSource;
use DirectoryTree\Screenshot\Screenshot as Factory;
use DirectoryTree\Screenshot\ScreenshotServiceProvider;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

test('a service provider configures defaults with a replaceable driver', function () {
    $driver = Mockery::mock(Driver::class);

    $driver->shouldReceive('capture')
        ->once()
        ->withArgs(fn ($source, $type, $options) => $source === 'hello' && $options->browser->getWidth() === 900)
        ->andReturn(new ImageResult(imageFixture()));

    app()->instance(Driver::class, $driver);

    config(['screenshot.capture.width' => 900, 'screenshot.capture.height' => 600]);

    expect(Screenshot::html('hello')->capture()->width())->toBe(1);
});

test('the facade fake replaces the container factory and renders escaped Blade views', function () {
    config(['screenshot.image.quality' => 85]);
    Screenshot::fake();

    Screenshot::assertNothingCaptured();

    app()->make(Factory::class)
        ->view('card', ['name' => '<Steve>'])
        ->jpeg()
        ->capture();

    Screenshot::assertCaptured(
        fn ($source, $type, $options) => str_contains($source, '&lt;Steve&gt;')
            && $type === SourceType::Html
            && $options->getFormat() === OutputFormat::Jpeg
            && $options->output->getQuality() === 85,
    );
    Screenshot::assertCapturedCount(1);
});

test('captures can be stored on named Laravel disks', function () {
    Storage::fake('screenshots');
    Screenshot::fake();

    $result = Screenshot::html('hello')->store('preview.png', 'screenshots');

    Storage::disk('screenshots')->assertExists('preview.png');
    expect(Storage::disk('screenshots')->get('preview.png'))->toBe($result->contents());
});

test('failed disk writes throw an exception', function () {
    Storage::shouldReceive('disk')->with(null)
        ->once()
        ->andReturnSelf();

    Storage::shouldReceive('put')->once()->andReturn(false);

    expect(fn () => (new ImageResult(imageFixture()))->store('preview.png'))
        ->toThrow(RuntimeException::class, 'Unable to store');
});

test('storage options control visibility for pending screenshots and captured results', function () {
    Storage::fake('screenshots');
    Screenshot::fake();

    $result = Screenshot::html('hello')->store(
        path: 'public.png',
        disk: 'screenshots',
        options: ['visibility' => 'public'],
    );
    $stored = $result->store(
        path: 'private.png',
        disk: 'screenshots',
        options: ['visibility' => 'private'],
    );

    expect(Storage::disk('screenshots')->getVisibility('public.png'))->toBe('public');
    expect(Storage::disk('screenshots')->getVisibility('private.png'))->toBe('private');
    expect(Storage::disk('screenshots')->get('private.png'))->toBe($result->contents());
    expect($stored)->toBe($result);
    Screenshot::assertCapturedCount(1);
});

test('disk-specific storage options are forwarded unchanged', function ($pending) {
    $result = new ImageResult(imageFixture());
    $options = ['visibility' => 'private', 'CacheControl' => 'max-age=3600', 'Metadata' => ['source' => 'screenshot']];
    Screenshot::fake($result);

    Storage::shouldReceive('disk')->once()
        ->with('screenshots')
        ->andReturnSelf();

    Storage::shouldReceive('put')->once()
        ->with('preview.png', $result->contents(), $options)
        ->andReturn(true);

    $source = $pending ? Screenshot::html('hello') : $result;

    expect($source->store(path: 'preview.png', disk: 'screenshots', options: $options))->toBe($result);
})->with(['pending screenshot' => true, 'captured result' => false]);

test('configuration has a publishable tag', function () {
    $paths = ServiceProvider::pathsToPublish(null, 'screenshot-config');

    expect(array_values($paths))->toContain(config_path('screenshot.php'));
});

test('the facade fake preserves configured socket defaults without starting a browser', function () {
    config(['screenshot.capture.socket' => 'emails']);
    Screenshot::fake();

    Screenshot::html('shared')->capture();
    Screenshot::html('dedicated')->withoutSocket()->capture();

    Screenshot::assertNothingClosed();

    Screenshot::close('emails');

    Screenshot::assertClosed(key: 'emails');
    Screenshot::assertNotClosed(key: 'invoices');

    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $source === 'shared'
            && $options->browser->getSocket() === 'emails',
    );
    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $source === 'dedicated'
            && $options->browser->getSocket() === null,
    );
    Screenshot::assertCapturedCount(2);

    Screenshot::fake();

    Screenshot::assertNothingClosed();
    Screenshot::assertNothingCaptured();

    Screenshot::url('https://example.com')->capture();

    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $type === SourceType::Url
            && $options->browser->getSocket() === 'emails',
    );
});

test('the configured socket directory is used for lifecycle operations', function () {
    $directory = sys_get_temp_dir().'/screenshot-config-'.bin2hex(random_bytes(8));
    config(['screenshot.sockets.directory' => $directory]);

    try {
        Screenshot::close('emails');

        $path = BrowserSocket::named('emails', $directory)->path();

        expect(file_exists($path.'.lock'))->toBeTrue();
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

test('partial socket configuration preserves the remaining package defaults', function () {
    config(['screenshot' => ['sockets' => ['lock_timeout' => 250]]]);

    (new ScreenshotServiceProvider(app()))->register();

    expect(config('screenshot.sockets.directory'))->toBe(storage_path('framework/cache/screenshots'));
    expect(config('screenshot.sockets.lock_timeout'))->toBe(250);
    expect(config('screenshot.browser.options'))->toBe([]);
});

test('partial browser configuration preserves installation and socket defaults', function () {
    config(['screenshot' => ['browser' => ['options' => ['startupTimeout' => 15]]]]);

    (new ScreenshotServiceProvider(app()))->register();

    expect(config('screenshot.browser.directory'))->toBe(storage_path('app/private/screenshot/browsers'));
    expect(config('screenshot.browser.options'))->toBe(['startupTimeout' => 15]);
    expect(config('screenshot.sockets.lock_timeout'))->toBe(60000);
});

test('the configured socket lock timeout bounds lifecycle operations', function () {
    $directory = sys_get_temp_dir().'/screenshot-timeout-'.bin2hex(random_bytes(8));
    config(['screenshot.sockets' => ['directory' => $directory, 'lock_timeout' => 10]]);

    mkdir($directory, 0700);

    $lease = fopen(BrowserSocket::named('emails', $directory)->path().'.usage', 'c');

    try {
        flock($lease, LOCK_SH);

        expect(fn () => Screenshot::close('emails'))
            ->toThrow(RuntimeException::class, 'Timed out waiting for browser socket lock');
    } finally {
        flock($lease, LOCK_UN);
        fclose($lease);

        (new Filesystem)->deleteDirectory($directory);
    }
});

test('PDF fakes render views and store the supplied document with filesystem options', function () {
    Storage::fake('pdfs');
    $result = new PdfResult(base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf')));
    config(['screenshot.capture.socket' => 'invoices']);
    Screenshot::fake($result);

    $stored = Screenshot::view('card', ['name' => '<Steve>'])->pdf()
        ->paper(PaperSize::Letter)
        ->store('invoice.pdf', 'pdfs', ['visibility' => 'private']);

    expect($stored)->toBe($result);
    expect(Storage::disk('pdfs')->get('invoice.pdf'))->toBe($result->contents());
    expect(Storage::disk('pdfs')->getVisibility('invoice.pdf'))->toBe('private');
    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $options->getFormat() === OutputFormat::Pdf
            && str_contains($source, '&lt;Steve&gt;')
            && $options->browser->getSocket() === 'invoices'
            && $options->output->getPaperWidth() === 215.9,
    );
    Screenshot::assertCapturedCount(1);

    Screenshot::fake();

    Screenshot::assertNothingCaptured();
});

test('a single fake result is reused only for matching output captures', function ($pdf, $named) {
    $result = $pdf
        ? new PdfResult(base64_encode(file_get_contents(__DIR__.'/../../resources/blank.pdf')))
        : new ImageResult(imageFixture());

    $fake = $named ? Screenshot::fake(result: $result) : Screenshot::fake($result);

    for ($i = 0; $i < 2; $i++) {
        $document = Screenshot::html('invoice')->pdf()->capture();
        $image = Screenshot::html('preview')->capture();
        $jpeg = Screenshot::html('preview')->jpeg()->capture();

        expect($pdf ? $document : $image)->toBe($result);
        expect($pdf ? $image : $document)->not->toBe($result);
        expect($image)->toBeInstanceOf(ImageResult::class);
        expect($document)->toBeInstanceOf(PdfResult::class);

        if (! $pdf) {
            expect($jpeg)->toBe($result);
        }
    }

    $fake->assertCapturedCount(6);
})->with(['image' => false, 'PDF' => true])->with(['positional' => false, 'named' => true]);

test('fake assertions receive the source type and element selector for every source and output format', function ($source, $format) {
    Screenshot::fake();

    $pending = match ($source) {
        'html' => Screenshot::html('<h1>Invoice</h1>'),
        'url' => Screenshot::url('https://example.com/invoice'),
        'view' => Screenshot::view('card', ['name' => 'Steve']),
    };

    $pending->element('#invoice');

    (match ($format) {
        OutputFormat::Png => $pending->png(),
        OutputFormat::Jpeg => $pending->jpeg(),
        OutputFormat::Pdf => $pending->pdf(),
    })->capture();

    expect($pending)->toBeInstanceOf(PendingSource::class);

    $expected = $source === 'url' ? SourceType::Url : SourceType::Html;
    $callback = fn (string $contents, SourceType $type, CaptureOptions $options) => $type === $expected
        && $options->getFormat() === $format
        && $options->element === '#invoice';

    Screenshot::assertCaptured($callback);
})->with(['html', 'url', 'view'])->with(OutputFormat::cases());

test('capture image and PDF configuration reaches independent pending captures', function () {
    config([
        'screenshot.capture.width' => 900,
        'screenshot.capture.height' => 600,
        'screenshot.capture.load_timeout' => 2000,
        'screenshot.capture.browser_timeout' => 4000,
        'screenshot.capture.javascript' => true,
        'screenshot.capture.fail_on_timeout' => true,
        'screenshot.capture.socket' => 'documents',
        'screenshot.image.format' => 'jpeg',
        'screenshot.image.quality' => 92,
        'screenshot.image.max_width' => 500,
        'screenshot.image.max_height' => 700,
        'screenshot.image.full_page' => false,
        'screenshot.pdf.paper' => 'letter',
        'screenshot.pdf.landscape' => true,
        'screenshot.pdf.margins' => ['top' => 1, 'right' => 2, 'bottom' => 3, 'left' => 4],
        'screenshot.pdf.background' => false,
        'screenshot.pdf.prefer_css_page_size' => true,
    ]);
    Screenshot::fake();
    $source = Screenshot::html('invoice');
    $pdf = $source->pdf();

    $source->size(320, 240)
        ->png()
        ->capture();
    $pdf->capture();
    Screenshot::html('next')->capture();

    Screenshot::assertCaptured(function ($source, $type, CaptureOptions $options) {
        if ($options->getFormat() !== OutputFormat::Pdf) {
            return false;
        }

        expect($options->browser->getWidth())->toBe(900);
        expect($options->browser->getHeight())->toBe(600);
        expect($options->browser->getLoadTimeout())->toBe(2000);
        expect($options->browser->getBrowserTimeout())->toBe(4000);
        expect($options->browser->getJavascript())->toBeTrue();
        expect($options->browser->getFailOnTimeout())->toBeTrue();
        expect($options->browser->getSocket())->toBe('documents');
        expect($options->output->getPaperWidth())->toBe(215.9);
        expect($options->output->getPaperHeight())->toBe(279.4);
        expect($options->output->getLandscape())->toBeTrue();
        expect($options->output->getMargins())->toBe(['top' => 1.0, 'right' => 2.0, 'bottom' => 3.0, 'left' => 4.0]);
        expect($options->output->getBackground())->toBeFalse();
        expect($options->output->getPreferCssPageSize())->toBeTrue();

        return true;
    });
    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $source === 'invoice'
            && $options->getFormat() === OutputFormat::Png
            && $options->browser->getWidth() === 320,
    );
    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $source === 'next'
            && $options->getFormat() === OutputFormat::Jpeg
            && $options->browser->getWidth() === 900
            && $options->output->getQuality() === 92
            && $options->output->getMaxWidth() === 500
            && $options->output->getMaxHeight() === 700
            && ! $options->output->getFullPage(),
    );
});

test('partial capture configuration inherits remaining defaults including individual PDF margins', function () {
    config(['screenshot' => [
        'capture' => ['width' => 800],
        'image' => ['quality' => 95],
        'pdf' => ['margins' => ['top' => 5]],
    ]]);
    (new ScreenshotServiceProvider(app()))->register();
    Screenshot::fake();

    Screenshot::html('image')->jpeg()->capture();
    Screenshot::html('pdf')->pdf()->capture();

    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $options->browser->getWidth() === 800
            && $options->browser->getHeight() === 768
            && $options->output->getQuality() === 95
            && $options->output->getFullPage(),
    );
    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $options->getFormat() === OutputFormat::Pdf
            && $options->output->getPaperWidth() === 210.0
            && $options->output->getMargins() === ['top' => 5.0, 'right' => 10.0, 'bottom' => 10.0, 'left' => 10.0],
    );
});

test('PDF defaults validate paper orientation and margins together', function () {
    config([
        'screenshot.pdf.paper' => 'a5',
        'screenshot.pdf.landscape' => true,
        'screenshot.pdf.margins.right' => 80,
        'screenshot.pdf.margins.left' => 80,
    ]);
    Screenshot::fake();

    Screenshot::html('landscape')->pdf()->capture();

    Screenshot::assertCaptured(
        fn ($source, $type, $options) => $options->getFormat() === OutputFormat::Pdf
            && $options->output->getPaperWidth() === 148.0
            && $options->output->getLandscape()
            && $options->output->getMargins()['left'] === 80.0,
    );
});

test('invalid configured defaults fail when the factory is resolved', function ($key, $value) {
    config([$key => $value]);

    expect(fn () => app(Factory::class))->toThrow(InvalidArgumentException::class);
})->with([
    'browser dimensions' => ['screenshot.capture.width', 0],
    'browser timeout' => ['screenshot.capture.browser_timeout', 0],
    'socket' => ['screenshot.capture.socket', ' '],
    'image quality' => ['screenshot.image.quality', 101],
    'image format' => ['screenshot.image.format', 'pdf'],
    'image limits' => ['screenshot.image.max_height', 20000],
    'print layout' => ['screenshot.pdf.margins.left', 300],
]);
