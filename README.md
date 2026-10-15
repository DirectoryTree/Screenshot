<h1 align="center">Screenshot</h1>

<p align="center">Capture images and PDFs with PHP and Laravel.</p>

<p align="center">
    <a href="https://github.com/DirectoryTree/Screenshot/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/DirectoryTree/Screenshot/tests.yml?branch=master&amp;style=flat-square" alt="Tests"></a>
    <a href="https://packagist.org/packages/directorytree/screenshot"><img src="https://img.shields.io/packagist/dt/directorytree/screenshot.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/directorytree/screenshot"><img src="https://img.shields.io/packagist/v/directorytree/screenshot.svg?style=flat-square" alt="Latest Version"></a>
    <a href="https://github.com/DirectoryTree/Screenshot/blob/master/LICENSE.md"><img src="https://img.shields.io/github/license/DirectoryTree/Screenshot?style=flat-square" alt="License"></a>
</p>

<p align="center">
    <a href="#installation">Installation</a>
    <span> · </span>
    <a href="#usage">Usage</a>
    <span> · </span>
    <a href="#shared-browsers-and-parallel-workers">Parallel Workers</a>
    <span> · </span>
    <a href="#configuration">Configuration</a>
    <span> · </span>
    <a href="#testing">Testing</a>
</p>

---

Screenshot captures HTML, Blade views, and web pages as PNG, JPEG, or PDF files using Chrome. Save captures locally or store them on any Laravel filesystem disk:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::view('invoices.preview', data: ['invoice' => $invoice])
    ->size(width: 1200, height: 800)
    ->jpeg(quality: 90)
    ->store(path: 'invoices/preview.jpg', disk: 's3');
```

## Requirements

- PHP 8.2+ and Laravel 12 or 13 for Laravel integration (PHP 8.3+ with Laravel 13)
- Chrome or Chromium, installable with `php artisan screenshot:install`

Screenshot uses [Chrome PHP](https://github.com/chrome-php/chrome). Node.js and Puppeteer are not required.

## Installation

Install the package with Composer:

```bash
composer require directorytree/screenshot
```

The service provider is registered automatically. Download Chrome with:

```bash
php artisan screenshot:install
```

The installer reuses an existing package-managed installation without checking for updates. If none exists, it downloads the latest stable Chrome for Testing for your platform. Downloads require PHP's `zip` extension and support available Linux, macOS, and Windows builds.

To explicitly check for and install the latest stable version:

```bash
php artisan screenshot:install --update
```

Chrome is stored in `storage/app/private/screenshot/browsers` and discovered automatically. Run the installer during deployment. To pin a browser version:

```bash
php artisan screenshot:install --browser-version=155.0.8059.79
```

A pinned version is reused when already downloaded. Use either `--update` or `--browser-version`, not both.

Alternatively, use an existing Chrome or Chromium executable:

```dotenv
SCREENSHOT_CHROME_PATH=/usr/bin/chromium
```

An explicit path takes precedence over downloaded Chrome. If neither is configured, Screenshot uses Chrome PHP's system discovery. After changing browsers, close shared browsers and restart long-running queue workers.

> When deploying to a server or Docker container, install Chrome's system libraries and any fonts your content needs. Alpine Linux and unsupported platforms may require system-provided Chromium.

## Usage

### HTML, Views, and URLs

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

$image = Screenshot::html('<h1>Hello, world!</h1>')->capture();

Screenshot::view('reports.summary', data: ['report' => $report])
    ->save(storage_path('app/summary.png'));

Screenshot::url('https://example.com')
    ->javascript()
    ->save(storage_path('app/example.png'));
```

Start with `html()`, `view()`, or `url()` to prepare a source. Select `png()`, `jpeg()`, or `pdf()`, apply the output settings, then call `capture()`, `save()`, or `store()`.

Calling `capture()`, `save()`, or `store()` directly on the source uses the configured image format, which defaults to PNG.

URLs must use HTTP or HTTPS. For HTML and views, use absolute asset URLs or provide a `<base href="...">` so Chrome can resolve images, stylesheets, and fonts.

JavaScript is disabled by default. Enable it with `javascript()` when needed. Captures wait for the page's load event; they do not wait for application-specific JavaScript work to finish.

### Dimensions

The default viewport is 1024 × 768 pixels. Full-page capture is enabled by default, cropped to a maximum of 2048 × 10000 pixels.

Viewport dimensions control page layout. Full-page capture sizes the image to the document without changing the viewport:

| Configuration | Effect |
| --- | --- |
| `capture.width` / `capture.height` | Sets the viewport used to lay out the page |
| `image.full_page` (`true`) | Captures the document within the maximum image bounds |
| `image.full_page` (`false`) | Captures only the viewport within the maximum image bounds |
| `image.max_width` / `image.max_height` | Crops the image without scaling it |

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::html($html)
    ->size(width: 1200, height: 800)
    ->png()
    ->maxSize(width: 1600, height: 6000)
    ->capture();

Screenshot::html($html)
    ->size(width: 1200, height: 800)
    ->png()
    ->viewport()
    ->capture();
```

Browser settings such as `size()` can be applied before or after selecting an output. Select `png()` or `jpeg()` before applying image settings such as `maxSize()`, `fullPage()`, or `viewport()`.

`size()` sets the layout viewport. `viewport()` captures only the visible viewport; `fullPage()` restores full-page capture. `maxSize()` crops from the top-left corner without scaling, and applies to both modes. Each dimension must be between 1 and 16384 pixels, with at most 40 million pixels per width/height pair.

### Image Formats

PNG is the default. JPEG accepts a quality from 0 to 100, defaulting to 80. Calling `jpeg()` without a quality uses the configured value:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

$image = Screenshot::html($html)->jpeg(quality: 85)->capture();
$image = Screenshot::html($html)->png()->capture();
```

File extensions do not select the format. Use `jpeg()` for JPEG output or `pdf()` for PDF output.

Switching between `png()` and `jpeg()` on an image builder preserves its other settings, including JPEG quality.

### Capturing an Element

Call `element()` on an HTML, view, or URL source before selecting an output. It captures the first element matching the CSS selector:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

$source = Screenshot::view('invoices.preview', ['invoice' => $invoice])
    ->size(1200, 800)
    ->element('#invoice');

$source->png()->save($imagePath);
$source->pdf()->save($pdfPath);
```

Images crop to the element's bounds, including padding and borders. The viewport still controls page layout. Element selection takes precedence over `fullPage()` and `viewport()`; `maxSize()` crops from the element's top-left corner.

PDFs move the selected element directly into the document body and print it using the configured paper, margins, print styles, and page breaks. Document stylesheets are retained. Styles inherited from removed wrappers or selectors that depend on those wrappers may need adjustment. The package does not reset the element's layout; use print CSS to control it.

Selection works with JavaScript disabled. Missing elements and invalid selectors throw exceptions. Images require an element with nonzero layout bounds; PDFs follow the document's print styles. Each output builder keeps the selector chosen when it was created.

### Saving and Reading Captures

Call `save()` or `store()` on a source or output builder, or capture once and reuse the result:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

$image = Screenshot::html($html)->capture();

$image->save(storage_path('app/preview.png'));
$image->store(path: 'previews/example.png', disk: 's3', options: ['visibility' => 'private']);

$image->contents(); // Raw image bytes
$image->base64();   // Base64-encoded image
$image->dataUri();  // data:image/png;base64,...
$image->mimeType(); // image/png or image/jpeg
$image->width();
$image->height();
```

`save()` requires an existing local directory. `store()` uses Laravel's default disk unless you specify one. Both overwrite an existing destination, return the result, and throw if writing fails.

Image captures return an `ImageResult`; PDF captures return a `PdfResult`. Both provide `contents()`, `base64()`, `dataUri()`, `mimeType()`, `save()`, and `store()`. Image results also provide `width()` and `height()`.

Sources, output builders, and captured results accept `options` on `store()`. These are passed directly to Laravel's filesystem, including visibility and any metadata supported by your disk.

Each call to a source or output builder's `capture()`, `save()`, or `store()` performs a new capture. Keep the result when you need to save or return the same image or document more than once.

### PDFs

Use `pdf()` to print HTML, a Blade view, or a URL as a PDF:

```php
use DirectoryTree\Screenshot\Enums\PaperSize;
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::view('invoices.preview', ['invoice' => $invoice])
    ->pdf()
    ->paper(PaperSize::Letter)
    ->landscape()
    ->store(path: 'invoices/invoice.pdf', disk: 's3');
```

PDFs default to A4 portrait, 10 mm margins, and printed backgrounds. `paper()` accepts `PaperSize::A3`, `A4`, `A5`, `Letter`, or `Legal`. Use `paperSize(width: 100, height: 150)` for custom dimensions in millimeters, `margins(top: 5, right: 5, bottom: 5, left: 5)` to set margins, and `background(false)` to omit backgrounds. `preferCssPageSize()` gives CSS `@page` sizes precedence over the selected paper size.

PDFs use Chrome's print layout, including print styles and page breaks. Screenshot viewport settings do not set the paper size; image format, quality, full-page mode, and `maxSize()` do not apply to PDF output.

Keep the source request when producing several outputs:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

$source = Screenshot::view('invoices.preview', ['invoice' => $invoice])
    ->size(1200, 800)
    ->socket('invoices');

$source->jpeg(90)->maxSize(1600, 6000)->save($imagePath);
$source->pdf()->save($pdfPath);
```

Select each output on the source. Each builder starts with the source's current browser settings, element selector, and its output defaults. Later changes to one builder do not affect the source or other builders.

### Timeouts

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::url('https://example.com')
    ->timeouts(load: 15000, browser: 60000)
    ->failOnTimeout()
    ->capture();
```

Both values are in milliseconds. Page loading defaults to 15 seconds and each browser command to 60 seconds. Omitted values keep their current settings, so `timeouts(load: 30000)` changes only the load timeout. These are separate limits, not an overall deadline. Allow enough time in your queue job for browser startup and capture.

By default, a page-load timeout captures what has rendered so far. Use `failOnTimeout()` to throw instead. Other browser failures always propagate.

## Shared Browsers and Parallel Workers

By default, each capture starts its own browser and closes it automatically. Use a named socket to reuse a browser across queue workers:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::html($html)
    ->socket(key: 'invoices')
    ->save($path);
```

Workers using the same key share one browser, while each capture gets its own page, dimensions, and JavaScript settings. Run captures in separate queue jobs or PHP processes for parallel execution; `socket()` does not dispatch work for you.

Shared browsers stay running between captures and after workers exit. You do not need to close them after each job. To deliberately shut one down, stop dispatching jobs for its key and call:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::close(key: 'invoices');
```

This waits for active captures before closing the browser. A later capture starts a new one. Close shared browsers when changing their binary or launch options, and recycle them after forcibly terminated workers.

A few things to keep in mind:

- Workers must run on the same host, under the same OS user, with the same private socket directory on a local filesystem supporting file locks. Sockets do not coordinate browsers across servers.
- Shared browsers share cookies and storage. Use separate keys or dedicated browsers when state must be isolated. Closing a browser retains its saved profile.
- Use consistent browser settings for each key. Do not remove socket files while workers are running or reuse one custom `userDataDir` across different keys.

To bypass a socket selected by your application defaults:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::html($html)->withoutSocket()->capture();
```

## Configuration

### Capture Defaults

Publish the [configuration file](config/screenshot.php) to set application defaults:

```bash
php artisan vendor:publish --tag=screenshot-config
```

The `capture` settings apply to both images and PDFs. The `image` and `pdf` settings control their respective outputs. For example, these entries configure a shared viewport, full-page image bounds, JPEG quality, and PDF paper size:

```php
// config/screenshot.php

return [
    'capture' => [
        'width' => 1280,
        'height' => 720,
        'socket' => 'captures',
    ],

    'image' => [
        'full_page' => true,
        'max_width' => 1600,
        'max_height' => 6000,
        'quality' => 85,
    ],

    'pdf' => [
        'paper' => 'letter',
    ],
];
```

Images use a 1280 × 720 viewport for layout and capture the document up to 1600 × 6000 pixels. Set `image.full_page` to `false` to capture only the viewport; the maximum image bounds still apply.

See the [configuration file](config/screenshot.php) for all available settings. Omitted entries use the package defaults. Capture dimensions are in pixels, timeouts are in milliseconds, and PDF margins are in millimeters. Use `socket => null` for dedicated browsers.

Fluent options override defaults for an individual capture. Restart long-running workers after changing configuration.

### Browser Settings

The same configuration file controls browser installation, launch options, and socket storage:

- `browser.binary`: an explicit Chrome executable, usually set through `SCREENSHOT_CHROME_PATH`.
- `browser.directory`: where the installer stores Chrome.
- `browser.options`: [Chrome PHP launch options](https://github.com/chrome-php/chrome#available-options), such as `startupTimeout` (in seconds).
- `sockets.directory`: the private directory shared by workers, defaulting to `storage/framework/cache/screenshots`.
- `sockets.lock_timeout`: how long to wait for locks or shared browser shutdown, in milliseconds; defaults to `60000` per wait.

Keep Chrome's sandbox enabled in production.

### Using PHP Without Laravel

HTML and URL capture work without a booted Laravel application:

```php
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Screenshot;

$screenshot = new Screenshot(new ChromeDriver(binary: '/usr/bin/chromium'));

$screenshot->html('<h1>Hello, world!</h1>')
    ->size(width: 800, height: 600)
    ->save(__DIR__.'/preview.png');
```

Pass `BrowserOptions`, `ImageOptions`, or `PdfOptions` objects through the `browser`, `image`, or `pdf` constructor arguments to set defaults. `view()` requires an injected Laravel view factory; `store()` requires a booted Laravel filesystem.

### Custom Drivers

The facade uses `ScreenshotManager` to resolve named drivers. Chrome is included as `chrome` and is the default. Calls such as `Screenshot::html()` are forwarded to the configured default driver:

```php
Screenshot::html('<h1>Hello!</h1>')->capture();
Screenshot::driver('chrome')->html('<h1>Hello!</h1>')->capture();
```

To register another driver, implement [`Driver`](src/Contracts/Driver.php) and call `extend()` in your service provider's `boot()` method:

```php
use App\Screenshots\RemoteDriver;
use DirectoryTree\Screenshot\Facades\Screenshot;
use Illuminate\Contracts\Foundation\Application;

public function boot(): void
{
    Screenshot::extend('remote', function (Application $app) {
        return $app->make(RemoteDriver::class);
    });
}
```

Select it for an individual capture:

```php
Screenshot::driver('remote')->url('https://example.com')->pdf()->capture();
```

Set `SCREENSHOT_DRIVER=remote` or `screenshot.default` to use it by default. Drivers are created on first use and cached by name. Every driver uses the same fluent builders and configured capture, image, and PDF defaults; custom drivers only implement the capture operation.

Inject `DirectoryTree\Screenshot\Contracts\Factory` when using the container directly. Both the manager and its fake implement this contract:

```php
use DirectoryTree\Screenshot\Contracts\Factory;
use DirectoryTree\Screenshot\ImageResult;

class InvoiceScreenshot
{
    public function __construct(
        protected Factory $screenshots,
    ) {}

    public function capture(string $html): ImageResult
    {
        return $this->screenshots->driver()->html($html)->capture();
    }
}
```

Outside Laravel, pass a driver to the standalone `Screenshot` constructor. Drivers supporting `close()` should also implement [`ManagesBrowsers`](src/Contracts/ManagesBrowsers.php).

Drivers receive `SourceType::Html` or `SourceType::Url` and handle every output through `capture(string $source, SourceType $type, CaptureOptions $options)`. Blade views are rendered to HTML first. `CaptureOptions` contains:

- `$options->browser()` is a `BrowserOptions` object containing viewport, timeout, JavaScript, and socket settings.
- `$options->output()` is an `ImageOptions` or `PdfOptions` object containing output settings.
- `$options->element()` is the selected CSS selector, or `null` for the whole page.

`$options->format()` returns `OutputFormat::Png`, `OutputFormat::Jpeg`, or `OutputFormat::Pdf`. Return an `ImageResult` for images or a `PdfResult` for PDFs. Each driver call receives an independent snapshot of both option objects.

## Testing

Use the facade fake to test your application without starting Chrome:

```php
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::fake();

Screenshot::html('<h1>Invoice</h1>')
    ->size(width: 800, height: 600)
    ->capture();

Screenshot::assertCapturedCount(count: 1);
Screenshot::assertCaptured(function (string $source, SourceType $type, CaptureOptions $options) {
    return $source === '<h1>Invoice</h1>' && $options->browser()->getWidth() === 800;
});
```

`Screenshot::fake()` intercepts both the default driver and explicit `driver()` selections without creating real drivers. Assertions collect captures and shutdown requests across all driver names.

`Screenshot::fake()` also replaces the `Contracts\Factory` container binding. Services resolved after faking receive the same recorder as the facade. Use this contract for constructor injection; the fake does not extend the concrete `ScreenshotManager` class.

`assertCaptured()` and `assertCapturedCount()` include PNG, JPEG, and PDF captures. Use `assertNothingCaptured()` when no captures should occur. Assert browser shutdown requests with `assertClosed($key)`, `assertNotClosed($key)`, and `assertNothingClosed()`.

Check the output format in the callback when it matters:

```php
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::fake();

Screenshot::html('<h1>Invoice</h1>')->pdf()->landscape()->capture();

Screenshot::assertCapturedCount(1);
Screenshot::assertCaptured(function (string $source, SourceType $type, CaptureOptions $options) {
    return $options->format() === OutputFormat::Pdf && $options->output()->getLandscape();
});
```

The fake preserves configured defaults and returns a fixed 1 × 1 PNG for images or a blank one-page PDF. Pass an `ImageResult` or `PdfResult` to `Screenshot::fake($result)` to supply a result for that output type.

Views still render and `save()` and `store()` still write files. Pair the fake with `Storage::fake()` when storing captures on Laravel disks.

## Handling Untrusted Content

Capture trusted content, or isolate Chrome with suitable network restrictions. Disabling JavaScript does not prevent HTML assets or redirects from accessing internal services. HTTP/HTTPS URL validation is not SSRF protection.
