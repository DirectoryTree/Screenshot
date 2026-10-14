<h1 align="center">Screenshot</h1>

<p align="center">Create browser screenshots with PHP and Laravel.</p>

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

Screenshot turns HTML, Blade views, and web pages into PNG or JPEG images using Chrome. Save screenshots locally or store them on any Laravel filesystem disk:

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

The installer requires PHP's `zip` extension and downloads the latest stable Chrome for Testing for your platform. It supports available Linux, macOS, and Windows builds.

Chrome is stored in `storage/app/private/screenshot/browsers` and discovered automatically. Run the installer during deployment. To pin a browser version:

```bash
php artisan screenshot:install --browser-version=155.0.8059.79
```

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

URLs must use HTTP or HTTPS. For HTML and views, use absolute asset URLs or provide a `<base href="...">` so Chrome can resolve images, stylesheets, and fonts.

JavaScript is disabled by default. Enable it with `javascript()` when needed. Captures wait for the page's load event; they do not wait for application-specific JavaScript work to finish.

### Dimensions

The default viewport is 1024 × 768 pixels. Full-page capture is enabled by default, cropped to a maximum of 2048 × 10000 pixels.

```php
Screenshot::html($html)
    ->size(width: 1200, height: 800)
    ->maxSize(width: 1600, height: 6000)
    ->fullPage()
    ->capture();

Screenshot::html($html)
    ->size(width: 1200, height: 800)
    ->viewport()
    ->capture();
```

`size()` sets the layout viewport. `maxSize()` crops the image from the top-left corner without scaling it, and applies to viewport captures too. Each dimension must be between 1 and 16384 pixels, with at most 40 million pixels per width/height pair.

### Image Formats

PNG is the default. JPEG accepts a quality from 0 to 100, defaulting to 80:

```php
$image = Screenshot::html($html)->jpeg(quality: 85)->capture();
$image = Screenshot::html($html)->png()->capture();
```

File extensions do not select the format. Use `jpeg()` when saving a `.jpg` file.

### Saving and Reading Images

Call `save()` or `store()` directly on a pending screenshot, or capture once and reuse the result:

```php
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

Both pending screenshots and captured results accept `options` on `store()`. These are passed directly to Laravel's filesystem, including visibility and any metadata supported by your disk.

Each call to a pending screenshot's `capture()`, `save()`, or `store()` takes a new screenshot. Keep the result when you need to save or return the same image more than once.

### Timeouts

```php
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
Screenshot::html($html)
    ->socket(key: 'invoices')
    ->save($path);
```

Workers using the same key share one browser, while each capture gets its own page, dimensions, and JavaScript settings. Run captures in separate queue jobs or PHP processes for parallel execution; `socket()` does not dispatch work for you.

Shared browsers stay running between captures and after workers exit. You do not need to close them after each job. To deliberately shut one down, stop dispatching jobs for its key and call:

```php
Screenshot::close(key: 'invoices');
```

This waits for active captures before closing the browser. A later capture starts a new one. Close shared browsers when changing their binary or launch options, and recycle them after forcibly terminated workers.

A few things to keep in mind:

- Workers must run on the same host, under the same OS user, with the same private socket directory on a local filesystem supporting file locks. Sockets do not coordinate browsers across servers.
- Shared browsers share cookies and storage. Use separate keys or dedicated browsers when state must be isolated. Closing a browser retains its saved profile.
- Use consistent browser settings for each key. Do not remove socket files while workers are running or reuse one custom `userDataDir` across different keys.

To bypass a socket selected by your application defaults:

```php
Screenshot::html($html)->withoutSocket()->capture();
```

## Configuration

### Capture Defaults

Configure default capture options in your `AppServiceProvider::boot()` method:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\PendingScreenshot;

public function boot(): void
{
    Screenshot::configure(
        fn (PendingScreenshot $screenshot) => $screenshot
            ->size(width: 1280, height: 720)
            ->jpeg(quality: 85)
            ->socket(key: 'invoices')
    );
}
```

The callback runs for each new screenshot. Use it to set options, then call `capture()`, `save()`, or `store()` outside the callback. Per-capture options override these defaults without affecting other screenshots:

```php
Screenshot::html($html)
    ->size(width: 800, height: 600)
    ->png()
    ->withoutSocket()
    ->capture();
```

Calling `configure()` again replaces the callback; `configure(null)` clears it. In long-running workers, register application defaults during boot and use per-capture methods for job-specific settings.

### Browser Settings

Publish the [configuration file](config/screenshot.php) to customize browser installation, launch options, and socket storage:

```bash
php artisan vendor:publish --tag=screenshot-config
```

- `browser.binary`: an explicit Chrome executable, usually set through `SCREENSHOT_CHROME_PATH`.
- `browser.directory`: where the installer stores Chrome.
- `browser.options`: [Chrome PHP launch options](https://github.com/chrome-php/chrome#available-options), such as `startupTimeout` (in seconds).
- `sockets.directory`: the private directory shared by workers, defaulting to `storage/framework/cache/screenshots`.
- `sockets.lock_timeout`: how long to wait for locks or shared browser shutdown, in milliseconds; defaults to `60000` per wait.

Set dimensions and operation timeouts through the fluent API. Screenshot manages browser lifetime automatically. Keep Chrome's sandbox enabled in production.

### Using PHP Without Laravel

HTML and URL capture work without a booted Laravel application:

```php
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Screenshot;

$screenshot = new Screenshot(new ChromeDriver(
    binary: '/usr/bin/chromium',
    socketDirectory: __DIR__.'/storage/screenshots',
));

$screenshot->html('<h1>Hello, world!</h1>')
    ->size(width: 800, height: 600)
    ->save(__DIR__.'/preview.png');
```

The same fluent options and `configure()` callback are available. `view()` requires an injected Laravel view factory; `store()` requires a booted Laravel filesystem.

### Custom Drivers

Chrome is the built-in driver. To supply your own, implement [`Driver`](src/Contracts/Driver.php) and bind it in your service provider's `register()` method, or pass it to the standalone `Screenshot` constructor. Drivers supporting `close()` should also implement [`ManagesBrowsers`](src/Contracts/ManagesBrowsers.php).

## Testing

Use the facade fake to test your application without starting Chrome:

```php
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Facades\Screenshot;
use Illuminate\Support\Facades\Storage;

Storage::fake('screenshots');
Screenshot::fake();

Screenshot::html('<h1>Invoice</h1>')
    ->size(width: 800, height: 600)
    ->socket(key: 'invoices')
    ->store(path: 'invoice.png', disk: 'screenshots');

Screenshot::assertCaptured(fn (string $source, string $type, CaptureOptions $options) =>
    $type === 'html'
    && $options->getWidth() === 800
    && $options->getSocket() === 'invoices'
);
Screenshot::assertCapturedCount(count: 1);
Screenshot::assertNothingClosed();
Storage::disk('screenshots')->assertExists('invoice.png');
```

Use `assertNothingCaptured()` when no screenshot should be taken. You can also assert explicit browser shutdown requests:

```php
Screenshot::close(key: 'invoices');

Screenshot::assertClosed(key: 'invoices');
Screenshot::assertNotClosed(key: 'reports');
```

The fake preserves configured defaults and returns a fixed 1 × 1 PNG. Pass a `ScreenshotResult` to `Screenshot::fake($result)` when specific output matters. Views still render and explicit file writes still happen, so pair it with `Storage::fake()` where appropriate.

## Handling Untrusted Content

Capture trusted content, or isolate Chrome with suitable network restrictions. Disabling JavaScript does not prevent HTML assets or redirects from accessing internal services. HTTP/HTTPS URL validation is not SSRF protection.

## Contributing

```bash
composer install
composer test
composer lint

CHROME_PATH=/usr/bin/chromium composer test-browser
```

Installer tests require PHP's `zip` extension. Set `SCREENSHOT_TEST_INSTALL=1` to include a real Chrome download. For disposable test containers that cannot use Chrome's sandbox, set `SCREENSHOT_NO_SANDBOX=1`.

## License

Screenshot is licensed under the [MIT license](LICENSE.md).
