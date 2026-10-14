<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chrome / Chromium
    |--------------------------------------------------------------------------
    |
    | Leave the binary unset to use downloaded Chrome, then Chrome PHP's
    | automatic discovery. Browser options are passed to Chrome PHP.
    | Browser lifetime, viewport and timeouts are managed by Screenshot.
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
    | Named sockets use this local directory. All workers sharing a browser
    | must use the same directory and OS user on the same host. Lock and
    | shutdown waits are bounded in milliseconds. Keep these files outside the web root.
    |
    */

    'sockets' => [
        'directory' => storage_path('framework/cache/screenshots'),
        'lock_timeout' => 60000,
    ],

];
