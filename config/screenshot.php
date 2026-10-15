<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | Captures use this driver unless one is selected explicitly. Chrome and
    | Gotenberg are included. Register custom drivers using Screenshot::extend().
    |
    */

    'default' => env('SCREENSHOT_DRIVER', 'chrome'),

    /*
    |--------------------------------------------------------------------------
    | Chrome / Chromium
    |--------------------------------------------------------------------------
    |
    | You may specify a Chrome or Chromium executable here. When unset,
    | Screenshot checks the install directory before falling back to
    | Chrome PHP's automatic browser discovery.
    |
    | The directory stores Chrome downloaded by screenshot:install. Browser
    | options are passed to Chrome PHP, while Screenshot manages the
    | browser lifetime, viewport dimensions and operation timeouts.
    |
    */

    'browser' => [
        'binary' => env('SCREENSHOT_CHROME_PATH'),
        'directory' => storage_path('app/private/screenshot/browsers'),
        'options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gotenberg
    |--------------------------------------------------------------------------
    |
    | HTTP options are passed to Guzzle. The JavaScript setting must match
    | the remote service and each capture; it does not configure Gotenberg.
    |
    */

    'gotenberg' => [
        'url' => env('SCREENSHOT_GOTENBERG_URL', 'http://localhost:3000'),
        'options' => [],
        'javascript' => env('SCREENSHOT_GOTENBERG_JAVASCRIPT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Browser Sockets
    |--------------------------------------------------------------------------
    |
    | Named sockets store their connection files in this private directory.
    | Workers sharing a browser must use the same directory and OS user
    | on the same host. Keep these files outside the public web root.
    |
    | The lock timeout limits each lock or browser shutdown wait, measured
    | in milliseconds. Increase it when captures need longer to finish.
    |
    */

    'sockets' => [
        'directory' => storage_path('framework/cache/screenshots'),
        'lock_timeout' => 60000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Capture Defaults
    |--------------------------------------------------------------------------
    |
    | Browser settings are shared by images and PDFs. Each pending capture
    | receives its own copy of these defaults. Fluent options override
    | them. Dimensions are in pixels and timeouts are in milliseconds.
    |
    */

    'capture' => [
        'width' => 1024,
        'height' => 768,
        'browser_timeout' => 60000,
        'load_timeout' => 15000,
        'javascript' => false,
        'fail_on_timeout' => false,
        'socket' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Defaults
    |--------------------------------------------------------------------------
    |
    | Images may use PNG or JPEG output. Quality applies to JPEG images only.
    | Maximum dimensions are in pixels and crop the image without scaling.
    |
    */

    'image' => [
        'format' => 'png',
        'quality' => 80,
        'max_width' => 2048,
        'max_height' => 10000,
        'full_page' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Defaults
    |--------------------------------------------------------------------------
    |
    | Here you may configure the default paper size, orientation, and margins
    | for PDF captures. Paper sizes are a0 through a6, letter, legal,
    | tabloid, or ledger. All margins are measured in millimeters.
    |
    */

    'pdf' => [
        'paper' => 'a4',
        'landscape' => false,
        'margins' => [
            'top' => 10,
            'right' => 10,
            'bottom' => 10,
            'left' => 10,
        ],
        'background' => true,
        'prefer_css_page_size' => false,
    ],

];
