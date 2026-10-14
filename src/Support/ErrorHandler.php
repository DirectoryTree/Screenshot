<?php

namespace DirectoryTree\Screenshot\Support;

class ErrorHandler
{
    /**
     * Suppress PHP errors during a callback and restore the previous handler.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function suppress(callable $callback): mixed
    {
        set_error_handler(static fn () => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
