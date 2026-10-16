<?php

namespace DirectoryTree\Screenshot\Support;

use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\BrowserConnectionFailed;
use HeadlessChromium\Exception\OperationTimedOut;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Wrench\Exception\SocketException;

class BrowserSocket
{
    /**
     * Create a file-backed browser socket. The file contains a WebSocket URI.
     */
    public function __construct(
        protected string $path,
        protected int $lockTimeout = 60000,
    ) {
        if (trim($path) === '' || str_contains($path, "\0") || str_contains($path, '://')) {
            throw new InvalidArgumentException('The socket path must be a local filesystem path.');
        }

        if ($lockTimeout < 1) {
            throw new InvalidArgumentException('The socket lock timeout must be greater than zero milliseconds.');
        }
    }

    /**
     * Resolve a key without allowing it to escape the socket directory.
     */
    public static function named(string $key, ?string $directory = null, int $lockTimeout = 60000): static
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('The socket key must not be empty.');
        }

        return new static(
            rtrim($directory ?? sys_get_temp_dir().'/directorytree-screenshot', '/\\').'/'.hash('sha256', $key).'.socket',
            $lockTimeout,
        );
    }

    /**
     * Get the socket file path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Run a capture while holding a shared lease on the browser.
     */
    public function run(callable $create, callable $callback, array $connectionOptions = []): mixed
    {
        return $this->lock('.usage', LOCK_SH, function () use ($create, $callback, $connectionOptions) {
            $browser = $this->lock('.lock', LOCK_EX, function () use ($create, $connectionOptions) {
                return $this->connectOrCreate($create, $connectionOptions);
            });

            try {
                return $callback($browser);
            } finally {
                // Disconnect this worker, leaving other pages and the browser alive.
                $this->disconnect($browser);
            }
        });
    }

    /**
     * Wait for active captures, then stop the browser and remove its socket.
     */
    public function close(array $connectionOptions = []): void
    {
        $this->lock('.usage', LOCK_EX, function () use ($connectionOptions) {
            $this->lock('.lock', LOCK_EX, function () use ($connectionOptions) {
                if ($uri = $this->read()) {
                    try {
                        $browser = $this->connect($uri, $connectionOptions);
                    } catch (BrowserConnectionFailed) {
                        $this->forget();

                        return;
                    }

                    try {
                        $browser->close();
                    } catch (SocketException $e) {
                        // Chrome can disconnect before acknowledging Browser.close.
                        // Retain the endpoint if a new connection still succeeds.
                        try {
                            $probe = $this->connect($uri, $connectionOptions);
                        } catch (BrowserConnectionFailed) {
                            $this->waitForShutdown($uri);
                            $this->forget();

                            return;
                        }

                        $this->disconnect($probe);

                        throw $e;
                    } finally {
                        $this->disconnect($browser);
                    }

                    $this->waitForShutdown($uri);
                }

                $this->forget();
            });
        });
    }

    /**
     * Keep the lifecycle lock until Chrome stops accepting connections.
     */
    protected function waitForShutdown(string $uri): void
    {
        $deadline = hrtime(true) + $this->lockTimeout * 1000000;

        while ($this->isListening($uri)) {
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('Timed out waiting for the shared browser to shut down.');
            }

            usleep(10000);
        }
    }

    /**
     * Check whether the browser endpoint is still accepting TCP connections.
     */
    protected function isListening(string $uri): bool
    {
        $parts = parse_url($uri);

        $address = 'tcp://'.$parts['host'].':'.($parts['port'] ?? ($parts['scheme'] === 'wss' ? 443 : 80));

        $connection = ErrorHandler::suppress(
            fn () => stream_socket_client($address, $errno, $error, 0.1)
        );

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }

    /**
     * Reuse an existing connection or atomically publish a replacement.
     */
    protected function connectOrCreate(callable $create, array $connectionOptions): Browser
    {
        if ($uri = $this->read()) {
            try {
                return $this->connect($uri, $connectionOptions);
            } catch (BrowserConnectionFailed|OperationTimedOut) {
                // Replace the saved endpoint only after a new browser starts.
            }
        }

        $browser = $create();

        try {
            $this->write($browser->getSocketUri());
        } catch (Throwable $e) {
            try {
                $browser->close();
            } catch (Throwable) {
                // Preserve the publication failure after attempting cleanup.
            }

            throw $e;
        }

        return $browser;
    }

    /**
     * Connect to a saved browser using this worker's command timeout.
     */
    protected function connect(string $uri, array $options): Browser
    {
        $previous = set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$previous) {
            if ($severity === E_WARNING && str_starts_with($message, 'stream_socket_client():')) {
                throw new BrowserConnectionFailed($message);
            }

            return $previous ? $previous($severity, $message, $file, $line) : false;
        });

        try {
            return BrowserFactory::connectToBrowser($uri, $options);
        } catch (SocketException $e) {
            throw new BrowserConnectionFailed('The saved browser is no longer responding.', previous: $e);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Read a socket while the startup lock is held.
     */
    protected function read(): ?string
    {
        clearstatcache(true, $this->path);

        if (! is_file($this->path)) {
            return null;
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read browser socket [{$this->path}].");
        }

        $uri = trim($contents);

        return Str::isUrl($uri, ['ws', 'wss']) ? $uri : null;
    }

    /**
     * Write the socket atomically, without exposing a partially written URI.
     */
    protected function write(string $uri): void
    {
        $temporary = tempnam(dirname($this->path), '.screenshot-');

        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary browser socket file.');
        }

        try {
            if (file_put_contents($temporary, $uri) !== strlen($uri) || ! rename($temporary, $this->path)) {
                throw new RuntimeException("Unable to write browser socket [{$this->path}].");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Remove the endpoint without deleting the stable lock files.
     */
    protected function forget(): void
    {
        clearstatcache(true, $this->path);

        if (is_file($this->path) && ! unlink($this->path)) {
            throw new RuntimeException("Unable to remove browser socket [{$this->path}].");
        }
    }

    /**
     * Release a worker's connection without masking a capture exception.
     */
    protected function disconnect(Browser $browser): void
    {
        try {
            $browser->getConnection()->disconnect();
        } catch (Throwable) {
            // A terminated browser may have already closed the connection.
        }
    }

    /**
     * Acquire a bounded advisory lock on the local filesystem.
     */
    protected function lock(string $suffix, int $operation, callable $callback): mixed
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create browser socket directory [{$directory}].");
        }

        $handle = fopen($this->path.$suffix, 'c');

        if ($handle === false) {
            throw new RuntimeException("Unable to open browser socket lock [{$this->path}{$suffix}].");
        }

        $deadline = hrtime(true) + $this->lockTimeout * 1000000;

        try {
            while (! flock($handle, $operation | LOCK_NB)) {
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException("Timed out waiting for browser socket lock [{$this->path}{$suffix}].");
                }

                usleep(10000);
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
