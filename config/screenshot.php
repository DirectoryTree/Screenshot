<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chrome / Chromium
    |--------------------------------------------------------------------------
    |
    | Leave the binary unset to use Chrome PHP's automatic discovery. Browser
    | options are passed to Chrome PHP. Each capture owns and closes its
    | browser, so keepAlive, windowSize and the command timeout are managed.
    |
    */

    'binary' => env('SCREENSHOT_CHROME_PATH'),

    'browser_options' => [],

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
    ],

];
