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

Screenshot::view('invoices.preview', data: ['invoice' => $invoice])
    ->size(width: 1200, height: 800)
    ->jpeg(quality: 90)
    ->store(path: 'invoices/preview.jpg', disk: 's3');
```

Screenshot is licensed under the [MIT license](LICENSE.md). The repository remains private while v1.0 is prepared for release.

## Requirements

- PHP 8.2 or higher (PHP 8.3 or higher for Laravel 13)
- Laravel 12 or 13 for the Laravel integration
- A locally installed Chrome or Chromium executable and its system libraries
- Permission for PHP to start processes and write browser temporary files

Screenshot uses [Chrome PHP](https://github.com/chrome-php/chrome). Node.js, Puppeteer, and a separate browser service are not required. Composer does not install Chrome. Use the installer below, or a current browser maintained by your operating system or deployment image.

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

Once a 1.x release is tagged, select the stable version constraint instead:

```bash
composer require directorytree/screenshot:^1.0
```

Keep the VCS repository configured until the package is available on Packagist. No stable release or Packagist publication is available during private review.

The service provider is registered automatically. Download Chrome for your operating system and architecture:

```bash
php artisan screenshot:install
```

The command downloads the latest stable [Chrome for Testing](https://github.com/GoogleChromeLabs/chrome-for-testing) directly from Google, extracts it, and checks that its executable can run. It requires PHP's `zip` extension and HTTPS access to `googlechromelabs.github.io` and `storage.googleapis.com`. Node.js and npm are not required.

Chrome is installed under `storage/app/private/screenshot/browsers`, configurable through `screenshot.browser.directory`. Screenshot automatically discovers the selected installation when the application boots. Install during deployment, using a directory readable and executable by your application workers; downloads never happen during a capture or Composer install.

To pin a complete browser version for reproducible deployments:

```bash
php artisan screenshot:install --browser-version=155.0.8059.79
```

Repeating a pinned installation reuses the downloaded version without contacting Google. Running the command without a version checks for the latest stable release and reuses it if already installed. Failed downloads leave the previously selected browser intact. Earlier versions are retained, allowing a previously downloaded version to be selected again with `--browser-version`.

The installer supports Google's available Linux x64 / ARM64, macOS Intel / Apple Silicon, and Windows x86 / x64 archives. Availability depends on the selected version. It does not install operating-system libraries or fonts, and checking `--version` does not guarantee that the browser can launch with your sandbox configuration. Provision those dependencies in your deployment image. Alpine Linux and unsupported architectures may require distribution-provided Chromium.

After changing the installed version, close any shared browsers and restart long-running queue workers. Old installation directories may be removed once no browsers or workers are using them.

An explicitly configured binary takes precedence over the downloaded installation. If neither is available, Screenshot falls back to Chrome PHP's normal system discovery. To use your own browser, set its executable path in your environment:

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
Screenshot::view('reports.summary', data: ['report' => $report])
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
    ->size(width: 1200, height: 800)
    ->maxSize(width: 1600, height: 6000)
    ->fullPage()
    ->capture();

Screenshot::html($html)
    ->size(width: 1200, height: 800)
    ->viewport()
    ->capture();
```

`size()` controls the layout viewport. `maxSize()` caps the captured image from its top-left corner; it crops rather than scales. These limits apply to viewport captures too. Each dimension must be between 1 and 16384 pixels, and each width/height pair must fit within 40 million pixels. Large documents can still consume browser memory before capture, so choose limits appropriate for your workload.

### Image Formats

PNG is the default. Select JPEG and its quality (0–100) explicitly:

```php
$image = Screenshot::html($html)->jpeg(quality: 85)->capture();
$image = Screenshot::html($html)->png()->capture();
```

File extensions do not select the format. Use `jpeg()` when saving a `.jpg` file. PNG captures ignore JPEG quality.

### Saving Images

```php
$image = Screenshot::html($html)->capture();

$image->save('/existing/directory/preview.png');
$image->store('previews/example.png');
$image->store(path: 'previews/example.png', disk: 's3');
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
    ->loadTimeout(milliseconds: 15000)
    ->timeout(milliseconds: 60000)
    ->capture();
```

All timeout values are milliseconds. The load timeout defaults to 15 seconds; the browser-operation timeout defaults to 60 seconds. They are separate limits, not a single wall-clock deadline. Browser startup has Chrome PHP's own `startupTimeout` option, in seconds.

When a page load times out, Screenshot stops loading external resources and captures what has rendered. Other browser failures propagate. To reject incomplete loads:

```php
Screenshot::url('https://example.com')
    ->failOnTimeout()
    ->capture();
```

Use an appropriate job timeout when capturing inside a queue worker. By default, each capture starts its own browser and closes the page and browser in a `finally` block. A failed page close falls back to closing the target. Shared browser mode keeps the browser running and closes only the capture’s page, including its target if page initialization fails.

## Shared Browsers and Parallel Workers

Use a named socket to reuse one browser across queue workers or PHP processes:

```php
Screenshot::html($html)
    ->socket(key: 'emails')
    ->size(width: 1024, height: 768)
    ->save($path);
```

Workers using the same key and socket directory connect to the same Chrome instance. Each capture gets its own page, viewport, and JavaScript settings. Captures can run at the same time; the startup lock is released before rendering begins. Different keys select different browsers.

After each capture, Screenshot closes its page and disconnects the worker, leaving the shared browser available for the next job. **You do not need to call `close()` after each screenshot or queue job.**

Screenshot stores Chrome's WebSocket address (`ws://...`) in a local file; it is not a Unix domain socket. If no live browser is available, Screenshot starts one and atomically saves its address. Concurrent startup uses filesystem locks, so cooperating workers do not create competing browsers. A dead or unresponsive saved connection is replaced on the next capture, subject to Chrome being able to start. Capture failures are not automatically retried.

A named key is hashed into a filename under `screenshot.sockets.directory`. In Laravel this defaults to `storage/framework/cache/screenshots`. Use a stable, private directory shared by the workers on the **same host**, with the same OS user and a local filesystem supporting `flock`. Keys do not use Laravel Cache or Redis and do not coordinate browsers across hosts or containers with different network namespaces. Do not delete or replace the directory or its lock files while workers are running.

To enable a named socket for every capture, register a [configuration callback](#capture-defaults) in `AppServiceProvider::boot()`:

```php
Screenshot::configure(
    fn (PendingScreenshot $screenshot) => $screenshot->socket(key: 'emails')
);
```

Import `DirectoryTree\Screenshot\PendingScreenshot` and the `Screenshot` facade. You can override the default on any capture with `socket()`, or opt out with `withoutSocket()`:

```php
Screenshot::html($html)->withoutSocket()->capture();
```

The API is synchronous within each PHP process. Run captures in separate Laravel queue jobs or PHP workers for parallel execution; selecting a socket does not dispatch jobs or add a batch scheduler. Limit worker concurrency to fit your browser host's memory and CPU.

### Browser Lifetime

Without a socket configured, Screenshot starts a dedicated browser and closes it automatically after the capture. A named socket enables browser reuse: Chrome stays running between captures, even after the PHP workers exit. Restarting a queue worker does not shut down its shared browser.

`close()` is an optional maintenance operation. Use it to deliberately stop a shared browser when it is no longer needed or when changing browser launch settings. Stop dispatching jobs that use the key before shutting it down:

```php
Screenshot::close(key: 'emails');
```

Closing waits for cooperating active captures to finish, requests graceful browser shutdown, waits for its endpoint to stop accepting connections, and removes the endpoint file. Chrome may finish flushing its profile asynchronously. A later capture starts a new browser. Lock and shutdown waits are bounded by `screenshot.sockets.lock_timeout` (60000 milliseconds by default, per wait); a lock timeout throws without closing active captures. Do not call shutdown from inside an active capture on the same socket.

Only use this lifecycle method for browsers managed by this application. If a worker is forcibly killed, its filesystem lease is released by the OS, but its page may remain open; drain workers and recycle the shared browser after such failures. Cleanup failures never cause a capture to terminate a browser being used by other workers.

Browser launch settings such as the binary, proxy, and custom flags are chosen by the worker that starts the browser. Use consistent configuration for a key and close/restart the browser after changing those settings. Dimensions, JavaScript, command timeouts, and configured HTTP headers are applied per worker/page.

**A shared browser also shares cookies and browser storage.** Use dedicated mode or separate keys for workloads requiring separate browser state. By default, a shared browser uses a profile directory beside its socket (`<socket>.profile`). Explicit shutdown retains this profile and the stable `.lock` / `.usage` files, so saved browser state may survive a restart. Remove them only after all workers have stopped and the browser is closed. A configured `screenshot.browser.options.userDataDir` overrides this profile path; never point different browser keys at the same profile directory.

## Configuration

The published `config/screenshot.php` contains browser infrastructure settings: the executable path, launch options, socket directory, and lock timeout:

```php
'browser' => [
    'binary' => env('SCREENSHOT_CHROME_PATH'),
    'directory' => storage_path('app/private/screenshot/browsers'),
    'options' => [
        'startupTimeout' => 30,
    ],
],

'sockets' => [
    'directory' => storage_path('framework/cache/screenshots'),
    'lock_timeout' => 60000,
],
```

Browser options use [Chrome PHP's option names](https://github.com/chrome-php/chrome#available-options). Screenshot always manages `keepAlive`, `windowSize`, and `sendSyncDefaultTimeout` to maintain capture lifetime and dimensions. Keep Chrome's sandbox enabled in production; `noSandbox` is available only as an explicit browser option for environments that require it.

### Capture Defaults

Register application-wide capture defaults in the `boot()` method of `AppServiceProvider` using the same fluent options as an individual screenshot:

```php
use DirectoryTree\Screenshot\Facades\Screenshot;
use DirectoryTree\Screenshot\PendingScreenshot;

public function boot(): void
{
    Screenshot::configure(
        fn (PendingScreenshot $screenshot) => $screenshot
            ->size(width: 1280, height: 720)
            ->jpeg(quality: 85)
            ->socket(key: 'emails')
    );
}
```

The callback runs once on each new pending screenshot created by `html()`, `url()`, or `view()`. It should only set options; call `capture()`, `save()`, or `store()` outside the callback. Returning the screenshot is optional.

Options apply in order: built-in defaults, the configuration callback, then per-capture overrides. Each screenshot receives its own options, so overrides do not affect subsequent captures:

```php
Screenshot::html($html)
    ->size(width: 800, height: 600)
    ->png()
    ->withoutSocket()
    ->capture();
```

Without a callback, screenshots use a 1024 × 768 viewport, PNG output, full-page capture bounded to 2048 × 10000 pixels, disabled JavaScript, and a dedicated browser. Browser operations time out after 60000 milliseconds; page loading times out after 15000 milliseconds and allows partial content by default. JPEG quality defaults to 80 when selected.

Calling `configure()` again replaces the callback for future screenshots. `Screenshot::configure(null)` clears it. Existing pending screenshots retain their options. Register application defaults during boot; use per-capture methods for request- or job-specific overrides in long-running workers.

Capture defaults are configured through this API, not through a `defaults` array in `config/screenshot.php`. When upgrading from the earlier preview, move those options into `configure()`, including any socket previously selected with `SCREENSHOT_SOCKET`. Keep callbacks in your service provider so the config file remains cacheable.

### Using PHP Without Laravel

HTML and URL capture, image conversion, and local saving do not require a booted Laravel application:

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

The same callback API is available without Laravel via `$screenshot->configure(...)`.

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
    ->size(width: 800, height: 600)
    ->store(path: 'invoice.png', disk: 'screenshots');

Screenshot::assertCaptured(fn (string $source, string $type, CaptureOptions $options) =>
    $type === 'html' && $options->getWidth() === 800
);
Screenshot::assertCapturedCount(count: 1);
Storage::disk('screenshots')->assertExists('invoice.png');
```

`Screenshot::assertNothingCaptured()` asserts that no terminal capture call was made. Socket keys are recorded on `CaptureOptions`; the fake does not start browsers, write socket files, or perform shutdown. The fake still renders Blade views and performs explicit file writes, so combine it with `Storage::fake()` when appropriate. It returns a fixed 1 × 1 PNG regardless of requested format or dimensions; pass a `ScreenshotResult` to `Screenshot::fake($result)` when specific output matters. Assertions require PHPUnit, which is included with Pest.

`CaptureOptions` exposes getters such as `getWidth()`, `getFormat()`, and `getSocket()` for assertions and custom drivers. Its properties are protected; use validated setters such as `setSize()`, `setFormat()`, and `setSocket()` when working with options directly. Dimension pairs and format/quality changes are validated before either value changes. The fluent methods on `PendingScreenshot` delegate to these setters.

Assert that a job selects the intended socket and leaves shared browsers running:

```php
Screenshot::fake();

Screenshot::html('<h1>Invoice</h1>')->socket(key: 'emails')->capture();

Screenshot::assertCaptured(fn ($source, $type, CaptureOptions $options) =>
    $options->getSocket() === 'emails'
);
Screenshot::assertNotClosed(key: 'emails');
Screenshot::assertNothingClosed();
```

The fake also records explicit shutdown requests, so you can test maintenance commands:

```php
Screenshot::close(key: 'emails');

Screenshot::assertClosed(key: 'emails');
Screenshot::assertNotClosed(key: 'invoices');
```

These assertions check calls to `close()` using exact socket keys; they do not verify a real browser's state. Captures and shutdown requests are recorded separately. The fake preserves the current configuration callback and applies it to each new pending screenshot, so assertions inspect the same configured options as real captures. Calling `Screenshot::fake()` again resets both records while preserving the configuration. You can also call `configure()` on the fake to replace or clear its callback.

The `DirectoryTree\Screenshot\Contracts\Driver` contract can also be replaced in Laravel's container or supplied to the standalone factory.

### Running the Package Tests

```bash
composer install
composer test
composer lint

CHROME_PATH=/usr/bin/chromium composer test-browser
```

Installer tests require PHP's `zip` extension. They cover platform detection, archive extraction, failed upgrades, installation locking, offline reuse, and command integration using local fixtures. To also download and capture with real Chrome from Google, run `SCREENSHOT_TEST_INSTALL=1 composer test-browser`.

The unit and Laravel tests cover validation, capture isolation, facade fakes, view rendering, storage failures, page-load timeouts, cleanup on failures, socket publication, and lock contention. Browser tests exercise separate concurrent PHP workers sharing one browser, independent page settings, reuse after worker exit, dead socket recovery, and shutdown. They use local fixtures and require a working Chrome executable. In a disposable test container that cannot enable the sandbox, opt in with `SCREENSHOT_NO_SANDBOX=1`.

CI runs PHP 8.2 / Laravel 12, PHP 8.3 / Laravel 13, and PHP 8.4 / Laravel 12 and 13. A separate Linux job exercises real Chromium captures and parallel workers. macOS and Windows jobs download Chrome and capture local HTML through the Laravel integration. Platform detection also recognizes Linux ARM64 and Windows x86; those architectures are not separately exercised in CI. A dependency audit must pass before changes are considered release-ready. No Packagist publication or release workflow is configured.
