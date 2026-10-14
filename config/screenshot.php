<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chrome / Chromium
    |--------------------------------------------------------------------------
    |
    | Leave the binary unset to use Chrome PHP's automatic discovery. Browser
    | options are passed to Chrome PHP. Browser lifetime, viewport size and
    | command timeouts are managed by Screenshot.
    |
    */

    'binary' => env('SCREENSHOT_CHROME_PATH'),

    'browser_options' => [],

    /*
    |--------------------------------------------------------------------------
    | Shared Browser Sockets
    |--------------------------------------------------------------------------
    |
    | Named sockets use this local directory. All workers sharing a browser
    | must use the same directory and OS user on the same host. Lock waits
    | are bounded in milliseconds. Keep these files outside the web root.
    |
    */

    'socket_directory' => storage_path('framework/cache/screenshots'),

    'socket_lock_timeout' => 60000,

    /*
    |--------------------------------------------------------------------------
    | Capture Defaults
    |--------------------------------------------------------------------------
    |
    | Dimensions are in pixels. Timeouts are in milliseconds. JavaScript is
    | disabled by default. Slow pages are stopped and captured as rendered
    | unless failOnTimeout is enabled. These limits crop rather than resize.
    |
    */

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

];
