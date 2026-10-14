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

Screenshot turns HTML, Blade views, and web pages into PNG or JPEG images using Chrome. Capture a document, save it locally, or store it on any Laravel filesystem disk:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

Screenshot::view('invoices.preview', ['invoice' => $invoice])
    ->size(1200, 800)
    ->jpeg(90)
    ->store('invoices/preview.jpg', 's3');
```

The package is currently private and unreleased. License selection is pending review; no open-source license has been granted.

## Requirements

- PHP 8.2 or higher (PHP 8.3 or higher for Laravel 13)
- Laravel 12 or 13 for the Laravel integration
- A locally installed Chrome or Chromium executable and its system libraries
- Permission for PHP to start processes and write browser temporary files

Screenshot uses [Chrome PHP](https://github.com/chrome-php/chrome). Node.js, Puppeteer, and a separate browser service are not required. Chrome must be installed separately; Composer does not install it. Use a current browser maintained by your operating system or deployment image.

## Installation

During private review, add the repository to your application's `composer.json`. Your Composer environment must already have read access to the private repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/DirectoryTree/Screenshot"
        }
    ]
}
```

Then install the review branch:

```bash
composer require directorytree/screenshot:dev-master
```

The service provider is registered automatically. If Chrome is not discovered automatically, set its executable path in your environment:

```dotenv
SCREENSHOT_CHROME_PATH=/usr/bin/chromium
```

You may publish the configuration:

```bash
php artisan vendor:publish --tag=screenshot-config
```

## Usage

### Capturing HTML

```php
use DirectoryTree\Screenshot\Facades\Screenshot;

$image = Screenshot::html('<h1>Hello, world!</h1>')->capture();
```

Use a complete HTML document for predictable styling. Relative image, stylesheet, and font URLs need an explicit `<base href="https://example.com/">` or absolute URLs. Embedded data URIs work without a network connection.

### Capturing Views

```php
Screenshot::view('reports.summary', ['report' => $report])
    ->save(storage_path('app/summary.png'));
```

Views are rendered through Laravel's view factory, including Blade escaping and your application's registered view paths.

### Capturing URLs

```php
Screenshot::url('https://example.com')
    ->javascript()
    ->save(storage_path('app/example.png'));
```

Only HTTP and HTTPS source URLs are accepted. JavaScript is disabled by default for both HTML and URLs. Enable it when needed. Captures wait for the browser's load event; arbitrary application-specific readiness conditions, logins, and browser automation are outside the package's scope.

### Dimensions

The default viewport is 1024 × 768 pixels. Full-page capture is enabled by default and cropped to a maximum of 2048 × 10000 pixels.

```php
Screenshot::html($html)
    ->size(1200, 800)
    ->maxSize(1600, 6000)
    ->fullPage()
    ->capture();

Screenshot::html($html)
    ->size(1200, 800)
    ->viewport()
    ->capture();
```

`size()` controls the layout viewport. `maxSize()` caps the captured image from its top-left corner; it crops rather than scales. These limits apply to viewport captures too. Each dimension must be between 1 and 16384 pixels, and each width/height pair must fit within 40 million pixels. Large documents can still consume browser memory before capture, so choose limits appropriate for your workload.

### Image Formats

PNG is the default. Select JPEG and its quality (0–100) explicitly:

```php
$image = Screenshot::html($html)->jpeg(85)->capture();
$image = Screenshot::html($html)->png()->capture();
```

File extensions do not select the format. Use `jpeg()` when saving a `.jpg` file. PNG captures ignore JPEG quality.

### Saving Images

```php
$image = Screenshot::html($html)->capture();

$image->save('/existing/directory/preview.png');
$image->store('previews/example.png');
$image->store('previews/example.png', 's3');
```

`save()` writes a local path in an existing directory. `store()` uses a Laravel disk, defaulting to your application's default disk. Both return the image result and throw if a write fails; existing files at the destination are overwritten. The pending capture also exposes `save()` and `store()` as shortcuts.

### Reading Images

```php
$image->contents(); // Raw image bytes
$image->base64();   // Base64 without a data URI prefix
$image->dataUri();  // data:image/png;base64,...
$image->mimeType(); // image/png or image/jpeg
$image->width();    // Actual captured width
$image->height();   // Actual captured height

return response($image->contents(), 200, [
    'Content-Type' => $image->mimeType(),
]);
```

Each call to `capture()`, or a pending capture's `save()` / `store()`, starts a new capture. Keep the returned result to reuse one image.

### Timeouts

```php
Screenshot::html($html)
    ->loadTimeout(15000)
    ->timeout(60000)
    ->capture();
```

All timeout values are milliseconds. The load timeout defaults to 15 seconds; the browser-operation timeout defaults to 60 seconds. They are separate limits, not a single wall-clock deadline. Browser startup has Chrome PHP's own `startupTimeout` option, in seconds.

When a page load times out, Screenshot stops loading external resources and captures what has rendered. Other browser failures propagate. To reject incomplete loads:

```php
Screenshot::url('https://example.com')
    ->failOnTimeout()
    ->capture();
```

Use an appropriate job timeout when capturing inside a queue worker. By default, each capture starts its own browser and closes the page and browser in a `finally` block. A failed page close falls back to closing the target. Shared browser mode keeps the browser running and closes only the capture’s page.

## Shared Browsers and Parallel Workers

Use a named socket to reuse one browser across queue workers or PHP processes:

```php
Screenshot::html($html)
    ->socket('emails')
    ->size(1024, 768)
    ->save($path);
```

Workers using the same key and socket directory connect to the same Chrome instance. Each capture gets its own page, viewport, and JavaScript settings. Captures can run at the same time; the startup lock is released before rendering begins. Different keys select different browsers.

You can also provide an explicit local socket file:

```php
Screenshot::html($html)
    ->socketPath(storage_path('app/chromium.socket'))
    ->capture();
```

The file contains Chrome's WebSocket address (`ws://...`); it is not a Unix domain socket. If no live browser is available, Screenshot starts one and atomically saves its address. Concurrent startup uses filesystem locks, so cooperating workers do not create competing browsers. A dead or unresponsive saved connection is replaced on the next capture, subject to Chrome being able to start. Capture failures are not automatically retried.

A named key is hashed into a filename under `screenshot.socket_directory`. In Laravel this defaults to `storage/framework/cache/screenshots`. Use a stable, private directory shared by the workers on the **same host**, with the same OS user and a local filesystem supporting `flock`. Keys do not use Laravel Cache or Redis and do not coordinate browsers across hosts or containers with different network namespaces. Do not delete or replace the directory or its lock files while workers are running. An explicit socket path should be absolute and identical in every worker.

To enable a named socket by default:

```dotenv
SCREENSHOT_SOCKET=emails
```

Or set `defaults.socket` / `defaults.socketPath` in `config/screenshot.php`, specifying only one. You can override the default on any capture with `socket()` or `socketPath()`, or opt out with `withoutSocket()`:

```php
Screenshot::html($html)->withoutSocket()->capture();
```

The API is synchronous within each PHP process. Run captures in separate Laravel queue jobs or PHP workers for parallel execution; selecting a socket does not dispatch jobs or add a batch scheduler. Limit worker concurrency to fit your browser host's memory and CPU.

### Browser Lifetime

Shared browsers outlive individual captures and worker exits. Close a shared browser explicitly when it is no longer needed:

```php
Screenshot::closeSocket('emails');
Screenshot::closeSocketPath(storage_path('app/chromium.socket'));
```

Closing waits for cooperating active captures to finish, requests graceful browser shutdown, waits for its endpoint to stop accepting connections, and removes the endpoint file. Chrome may finish flushing its profile asynchronously. A later capture starts a new browser. Lock and shutdown waits are bounded by `screenshot.socket_lock_timeout` (60000 milliseconds by default, per wait); a lock timeout throws without closing active captures. Stop dispatching new jobs before shutdown to avoid continued readers delaying it. Do not call shutdown from inside an active capture on the same socket.

Only use these lifecycle methods for browsers managed by this application. If a worker is forcibly killed, its filesystem lease is released by the OS, but its page may remain open; drain workers and recycle the shared browser after such failures. Cleanup failures never cause a capture to terminate a browser being used by other workers.

Browser launch settings such as the binary, proxy, and custom flags are chosen by the worker that starts the browser. Use consistent configuration for a key and close/restart the browser after changing those settings. Dimensions, JavaScript, command timeouts, and configured HTTP headers are applied per worker/page.

**A shared browser also shares cookies and browser storage.** Use dedicated mode or separate keys for workloads requiring separate browser state. By default, a shared browser uses a profile directory beside its socket (`<socket>.profile`). Explicit shutdown retains this profile and the stable `.lock` / `.usage` files, so saved browser state may survive a restart. Remove them only after all workers have stopped and the browser is closed. A configured `browser_options.userDataDir` overrides this profile path; never point different browser keys at the same profile directory.

## Configuration

The published `config/screenshot.php` contains the executable path, browser options, and capture defaults:

```php
'binary' => env('SCREENSHOT_CHROME_PATH'),

'browser_options' => [
    'startupTimeout' => 30,
],

'socket_directory' => storage_path('framework/cache/screenshots'),
'socket_lock_timeout' => 60000,

'defaults' => [
    'width' => 1024,
    'height' => 768,
    'maxWidth' => 2048,
    'maxHeight' => 10000,
    'format' => 'png',
    'quality' => 80,
    'timeout' => 60000,
    'loadTimeout' => 15000,
    'fullPage' => true,
    'javascript' => false,
    'failOnTimeout' => false,
    'socket' => env('SCREENSHOT_SOCKET'),
    'socketPath' => null,
],
```

Browser options use [Chrome PHP's option names](https://github.com/chrome-php/chrome#available-options). Screenshot always manages `keepAlive`, `windowSize`, and `sendSyncDefaultTimeout` to maintain capture lifetime and dimensions. Keep Chrome's sandbox enabled in production; `noSandbox` is available only as an explicit browser option for environments that require it.

### Using PHP Without Laravel

HTML and URL capture, image conversion, and local saving do not require a booted Laravel application:

```php
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Screenshot;

$screenshot = new Screenshot(new ChromeDriver(
    '/usr/bin/chromium',
    socketDirectory: __DIR__.'/storage/screenshots',
));

$screenshot->html('<h1>Hello, world!</h1>')
    ->size(800, 600)
    ->save(__DIR__.'/preview.png');
```

Named standalone sockets default to `sys_get_temp_dir().'/directorytree-screenshot'`; set an application-specific `socketDirectory` as shown above when sharing browsers.

Laravel components are Composer dependencies, but standalone captures do not use the container or facades. `view()` requires an injected `Illuminate\Contracts\View\Factory`; `store()` requires a booted Laravel filesystem.

### Handling Untrusted Content

Disabling JavaScript is not a network security boundary. HTML images, styles, fonts, redirects, and URL navigation can still access destinations reachable by the browser. Only capture trusted content, or run Chrome in an isolated environment with network restrictions appropriate for untrusted input. Source URL validation does not prevent SSRF or validate redirect destinations.

## Testing

Use the facade fake to test application behavior without launching Chrome:

```php
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Facades\Screenshot;
use Illuminate\Support\Facades\Storage;

Storage::fake('screenshots');
Screenshot::fake();

Screenshot::html('<h1>Invoice</h1>')
    ->size(800, 600)
    ->store('invoice.png', 'screenshots');

Screenshot::assertCaptured(fn (string $source, string $type, CaptureOptions $options) =>
    $type === 'html' && $options->width === 800
);
Screenshot::assertCapturedCount(1);
Storage::disk('screenshots')->assertExists('invoice.png');
```

`Screenshot::assertNothingCaptured()` asserts that no terminal capture call was made. Socket selectors are recorded on `CaptureOptions`; the fake does not start browsers, write socket files, or perform shutdown. The fake still renders Blade views and performs explicit file writes, so combine it with `Storage::fake()` when appropriate. It returns a fixed 1 × 1 PNG regardless of requested format or dimensions; pass a `ScreenshotResult` to `Screenshot::fake($result)` when specific output matters. Assertions require PHPUnit, which is included with Pest.

The `DirectoryTree\Screenshot\Contracts\Driver` contract can also be replaced in Laravel's container or supplied to the standalone factory.

### Running the Package Tests

```bash
composer install
composer test
composer lint

CHROME_PATH=/usr/bin/chromium composer test-browser
```

The unit and Laravel tests cover validation, capture isolation, facade fakes, view rendering, storage failures, page-load timeouts, cleanup on failures, socket publication, and lock contention. Browser tests exercise separate concurrent PHP workers sharing one browser, independent page settings, reuse after worker exit, dead socket recovery, and shutdown. They use local fixtures and require a working Chrome executable. In a disposable test container that cannot enable the sandbox, opt in with `SCREENSHOT_NO_SANDBOX=1`.

CI runs PHP 8.2 / Laravel 12, PHP 8.3 / Laravel 13, and PHP 8.4 / Laravel 12 and 13. A separate job exercises real Chromium captures. No Packagist publication or release workflow is configured.
