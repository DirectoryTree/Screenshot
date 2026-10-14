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

];
