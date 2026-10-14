<?php

return [

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
    | receives its own copy of these defaults. Fluent options override them.
    | Dimensions are in pixels and timeouts are in milliseconds.
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
    | Paper sizes are a3, a4, a5, letter, or legal. Margins are in millimeters.
    | Selecting PDF output copies the source's current browser settings and
    | these print defaults into an independent PDF builder.
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
