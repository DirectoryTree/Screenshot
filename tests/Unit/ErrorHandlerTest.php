<?php

use DirectoryTree\Screenshot\Support\ErrorHandler;

test('suppression returns the callback result and restores the previous handler', function () {
    $warnings = [];
    $result = new stdClass;
    set_error_handler(function ($severity, $message) use (&$warnings) {
        $warnings[] = $message;

        return true;
    });

    try {
        $returned = ErrorHandler::suppress(function () use ($result) {
            trigger_error('suppressed', E_USER_WARNING);

            return $result;
        });
        trigger_error('restored', E_USER_WARNING);

        expect($returned)->toBe($result)->and($warnings)->toBe(['restored']);
    } finally {
        restore_error_handler();
    }
});

test('suppression propagates exceptions and restores the previous handler', function () {
    $warnings = [];
    $exception = new RuntimeException('callback failed');
    set_error_handler(function ($severity, $message) use (&$warnings) {
        $warnings[] = $message;

        return true;
    });

    try {
        try {
            ErrorHandler::suppress(fn () => throw $exception);
        } catch (RuntimeException $caught) {
            expect($caught)->toBe($exception);
        }

        trigger_error('restored after failure', E_USER_WARNING);
        expect($caught ?? null)->toBe($exception)
            ->and($warnings)->toBe(['restored after failure']);
    } finally {
        restore_error_handler();
    }
});

test('nested suppression restores each enclosing handler', function () {
    $warnings = [];
    set_error_handler(function ($severity, $message) use (&$warnings) {
        $warnings[] = $message;

        return true;
    });

    try {
        ErrorHandler::suppress(function () {
            ErrorHandler::suppress(fn () => trigger_error('inner', E_USER_WARNING));
            trigger_error('outer', E_USER_WARNING);
        });
        trigger_error('restored', E_USER_WARNING);

        expect($warnings)->toBe(['restored']);
    } finally {
        restore_error_handler();
    }
});
