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

    'binary' => env('SCREENSHOT_CHROME_PATH'),

    'browser_directory' => storage_path('app/private/screenshot/browsers'),

    'browser_options' => [],

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

    'socket_directory' => storage_path('framework/cache/screenshots'),

    'socket_lock_timeout' => 60000,

];
