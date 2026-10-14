<?php

use DirectoryTree\Screenshot\Support\BrowserSocket;
use HeadlessChromium\Browser;
use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Exception\BrowserConnectionFailed;
use HeadlessChromium\Exception\OperationTimedOut;
use Illuminate\Filesystem\Filesystem;
use Wrench\Exception\SocketException;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/screenshot-sockets-'.bin2hex(random_bytes(8));
    mkdir($this->directory, 0700);
    $this->path = $this->directory.'/browser.socket';
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->directory);
});

function socketBrowser(): ProcessAwareBrowser
{
    $browser = Mockery::mock(ProcessAwareBrowser::class);
    $connection = Mockery::mock(Connection::class);
    $browser->shouldReceive('getConnection')->andReturn($connection);
    $connection->shouldReceive('disconnect')->once();

    return $browser;
}

function socketWithConnector(string $path, callable $connect): BrowserSocket
{
    return new class($path, $connect) extends BrowserSocket
    {
        public function __construct(string $path, protected mixed $connector)
        {
            parent::__construct($path);
        }

        protected function connect(string $uri, array $options): Browser
        {
            return ($this->connector)($uri, $options);
        }

        protected function waitForShutdown(string $uri): void {}
    };
}

test('named keys cannot escape their configured directory', function () {
    $socket = BrowserSocket::named('../../secret', $this->directory);

    expect(dirname($socket->path()))->toBe($this->directory)
        ->and($socket->path())->toBe(BrowserSocket::named('../../secret', $this->directory)->path())
        ->and($socket->path())->not->toBe(BrowserSocket::named('different', $this->directory)->path());
});

test('a created socket is published and its browser is disconnected rather than closed', function () {
    $browser = socketBrowser();
    $browser->shouldReceive('getSocketUri')->once()->andReturn('ws://127.0.0.1:9222/devtools/browser/test');
    $browser->shouldNotReceive('close');

    $result = (new BrowserSocket($this->path))->run(fn () => $browser, fn ($connected) => $connected === $browser);

    expect($result)->toBeTrue()
        ->and(file_get_contents($this->path))->toBe('ws://127.0.0.1:9222/devtools/browser/test');
});

test('existing sockets are reused with each callers connection options', function () {
    file_put_contents($this->path, 'ws://127.0.0.1:9222/devtools/browser/existing');
    $browser = socketBrowser();
    $socket = socketWithConnector($this->path, function ($uri, $options) use ($browser) {
        expect($uri)->toBe('ws://127.0.0.1:9222/devtools/browser/existing')
            ->and($options['sendSyncDefaultTimeout'])->toBe(1234);

        return $browser;
    });

    $socket->run(fn () => throw new RuntimeException('must not create'), fn () => null, ['sendSyncDefaultTimeout' => 1234]);
});

test('stale sockets are replaced while holding the startup lock', function () {
    file_put_contents($this->path, 'ws://127.0.0.1:9222/devtools/browser/stale');
    $browser = socketBrowser();
    $browser->shouldReceive('getSocketUri')->once()->andReturn('ws://127.0.0.1:9222/devtools/browser/new');
    $socket = socketWithConnector($this->path, fn () => throw new BrowserConnectionFailed);

    $socket->run(fn () => $browser, fn () => null);

    expect(file_get_contents($this->path))->toBe('ws://127.0.0.1:9222/devtools/browser/new');
});

test('publication failure closes the newly started browser', function () {
    $browser = Mockery::mock(ProcessAwareBrowser::class);
    $browser->shouldReceive('getSocketUri')->once()->andReturn('ws://localhost/test');
    $browser->shouldReceive('close')->once();
    $socket = new class($this->path) extends BrowserSocket
    {
        protected function write(string $uri): void
        {
            throw new RuntimeException('publication failed');
        }
    };

    expect(fn () => $socket->run(fn () => $browser, fn () => null))->toThrow(RuntimeException::class, 'publication failed');
});

test('capture failures release connections and usage locks without closing the shared browser', function () {
    $browser = socketBrowser();
    $browser->shouldReceive('getSocketUri')->once()->andReturn('ws://localhost/test');
    $browser->shouldNotReceive('close');
    $socket = new BrowserSocket($this->path);

    expect(fn () => $socket->run(fn () => $browser, fn () => throw new RuntimeException('capture failed')))
        ->toThrow(RuntimeException::class, 'capture failed');

    $handle = fopen($this->path.'.usage', 'c');
    expect(flock($handle, LOCK_EX | LOCK_NB))->toBeTrue();
    fclose($handle);
});

test('startup lock contention times out without creating a second browser', function () {
    $handle = fopen($this->path.'.lock', 'c');
    flock($handle, LOCK_EX);

    try {
        expect(fn () => (new BrowserSocket($this->path, 20))->run(
            fn () => throw new LogicException('must not create'), fn () => null,
        ))->toThrow(RuntimeException::class, 'Timed out waiting');
    } finally {
        fclose($handle);
    }
});

test('shutdown cannot close a browser with an active capture lease', function () {
    file_put_contents($this->path, 'ws://localhost/test');
    $handle = fopen($this->path.'.usage', 'c');
    flock($handle, LOCK_SH);

    try {
        expect(fn () => (new BrowserSocket($this->path, 20))->close())->toThrow(RuntimeException::class, 'Timed out waiting');
        expect(file_exists($this->path))->toBeTrue();
    } finally {
        fclose($handle);
    }
});

test('shutdown closes an idle browser and preserves stable lock files', function () {
    file_put_contents($this->path, 'ws://localhost/test');
    $browser = socketBrowser();
    $browser->shouldReceive('close')->once();
    socketWithConnector($this->path, fn () => $browser)->close();

    expect(file_exists($this->path))->toBeFalse()
        ->and(file_exists($this->path.'.lock'))->toBeTrue()
        ->and(file_exists($this->path.'.usage'))->toBeTrue();
});

test('shutdown can remove an already dead socket', function () {
    file_put_contents($this->path, 'ws://localhost/test');
    socketWithConnector($this->path, fn () => throw new BrowserConnectionFailed)->close();

    expect(file_exists($this->path))->toBeFalse();
});

test('a failed replacement retains the previous socket for a later retry', function () {
    file_put_contents($this->path, 'ws://localhost/previous');
    $socket = socketWithConnector($this->path, fn () => throw new BrowserConnectionFailed);

    expect(fn () => $socket->run(fn () => throw new RuntimeException('startup failed'), fn () => null))
        ->toThrow(RuntimeException::class, 'startup failed');
    expect(file_get_contents($this->path))->toBe('ws://localhost/previous');
});

test('corrupt socket files are replaced without attempting an invalid connection', function () {
    file_put_contents($this->path, 'not a websocket address');
    $browser = socketBrowser();
    $browser->shouldReceive('getSocketUri')->once()->andReturn('ws://localhost/replacement');
    $socket = socketWithConnector($this->path, fn () => throw new LogicException('must not connect'));
    $socket->run(fn () => $browser, fn () => null);

    expect(file_get_contents($this->path))->toBe('ws://localhost/replacement');
});

test('shutdown does not forget a browser when its response times out', function () {
    file_put_contents($this->path, 'ws://localhost/unresponsive');
    $socket = socketWithConnector($this->path, fn () => throw new OperationTimedOut);

    expect(fn () => $socket->close())->toThrow(OperationTimedOut::class);
    expect(file_exists($this->path))->toBeTrue();
});

test('shutdown accepts a disconnect after Chrome has stopped', function () {
    file_put_contents($this->path, 'ws://localhost/closing');
    $browser = socketBrowser();
    $browser->shouldReceive('close')->once()->andThrow(new SocketException('disconnected'));
    $attempt = 0;
    $socket = socketWithConnector($this->path, function () use ($browser, &$attempt) {
        if ($attempt++ === 0) {
            return $browser;
        }

        throw new BrowserConnectionFailed;
    });

    $socket->close();
    expect(file_exists($this->path))->toBeFalse();
});

test('shutdown retains the socket when a disconnected browser is still reachable', function () {
    file_put_contents($this->path, 'ws://localhost/running');
    $browser = socketBrowser();
    $browser->shouldReceive('close')->once()->andThrow(new SocketException('disconnected'));
    $probe = socketBrowser();
    $attempt = 0;
    $socket = socketWithConnector($this->path, function () use ($browser, $probe, &$attempt) {
        return $attempt++ === 0 ? $browser : $probe;
    });

    expect(fn () => $socket->close())->toThrow(SocketException::class);
    expect(file_exists($this->path))->toBeTrue();
});

test('shutdown waits for the listening endpoint to disappear', function () {
    $socket = new class($this->path, 100) extends BrowserSocket
    {
        /**
         * The number of browser endpoint checks performed during shutdown.
         *
         * @var int Starts at zero before the first probe.
         */
        public int $checks = 0;

        public function wait(): void
        {
            $this->waitForShutdown('ws://localhost/browser');
        }

        protected function isListening(string $uri): bool
        {
            return ++$this->checks < 3;
        }
    };

    $socket->wait();
    expect($socket->checks)->toBe(3);
});

test('shutdown waits are bounded when the endpoint remains open', function () {
    $socket = new class($this->path, 20) extends BrowserSocket
    {
        public function wait(): void
        {
            $this->waitForShutdown('ws://localhost/browser');
        }

        protected function isListening(string $uri): bool
        {
            return true;
        }
    };

    expect(fn () => $socket->wait())->toThrow(RuntimeException::class, 'Timed out waiting for the shared browser');
});
