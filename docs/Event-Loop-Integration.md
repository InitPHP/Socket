# Event Loop Integration

`live()` is convenient but greedy — it never yields control. If you already run an event loop (ReactPHP, Amp, Symfony's Console Application, an HTTP server, a queue worker), drive the server from your own loop via `tick()`.

## The `tick()` contract

```php
public function tick(callable $callback, float $waitSeconds = 0.0): int;
```

- Performs **exactly one** `socket_select` / `stream_select` call with `$waitSeconds` as the timeout.
- Accepts at most one pending connection and services every readable existing client.
- Returns the number of events handled (`0` if `select()` timed out with no readiness).
- Throws `SocketException` if called before `listen()`.

`live()` is implemented as

```php
public function live(callable $callback, float $idleSeconds = 0.05): void
{
    $this->running = true;
    while ($this->isRunning()) {
        $this->tick($callback, $idleSeconds);
    }
}
```

so there is nothing magical about it — you can always re-implement it.

## Three integration patterns

### 1. Polling inside a host loop

Your scheduler decides when to yield; the server gets a tiny slice each tick:

```php
$server->listen();

while ($host->isRunning()) {
    $events = $server->tick(
        fn ($srv, $conn) => $myHandler($srv, $conn),
        waitSeconds: 0.0,   // non-blocking; return immediately if no readiness
    );
    if ($events === 0) {
        $host->yield();
    }
}

$server->close();
```

Use `waitSeconds: 0.0` when the host loop has its own idle strategy. The socket layer never blocks; it just reports whether work was found.

### 2. Time-budgeted batches

Give the server a fixed slice of wall-clock time per host tick:

```php
$server->listen();

while ($host->isRunning()) {
    $deadline = microtime(true) + 0.01;   // 10 ms budget per host tick
    do {
        $events = $server->tick(callback, waitSeconds: 0.0);
    } while ($events > 0 && microtime(true) < $deadline);

    $host->yield();
}
```

Useful inside a single-process app where you want bounded socket latency without the socket layer monopolising the CPU.

### 3. Blocking select with an external wake-up

If the host has nothing else to do (a pure socket worker), let `tick()` block on `select()` and signal it externally to wake up:

```php
$server->listen();

pcntl_signal(SIGTERM, fn () => $server->stop());
pcntl_signal(SIGINT,  fn () => $server->stop());

while ($server->isRunning()) {
    $server->tick(callback, waitSeconds: 1.0);
    pcntl_signal_dispatch();
}

$server->close();
```

`tick()` returns once a second even when idle, giving the signal dispatcher a chance to flip `$running` to `false`.

> Without `pcntl_async_signals(true)`, signals are only processed at `pcntl_signal_dispatch()`. Inside a busy `select()` they queue up and fire on the next `tick()` return.

## Calling `tick()` from the callback

The callback may call `tick()` reentrantly — but each call re-enters `select()`, which is rarely what you want. The conventional pattern is to set state from the callback and let the outer loop pick it up:

```php
$server->live(function ($srv, $conn) {
    if ($conn->read() === 'shutdown') {
        $srv->stop();    // outer live() will exit on the next iteration
    }
});
```

`stop()` is the safe way to break out of `live()` mid-callback.

## Mixing servers in one loop

There is no shared scheduler — each server keeps its own `select()` call. Drive them sequentially in the host loop:

```php
$tcp = Socket::server(Transport::TCP, '0.0.0.0', 8080);
$udp = Socket::server(Transport::UDP, '0.0.0.0', 8081);
$tcp->listen();
$udp->listen();

while ($host->running) {
    $tcp->tick($onTcp, waitSeconds: 0.0);
    $udp->tick($onUdp, waitSeconds: 0.0);
    if (! $host->hasOtherWork()) {
        usleep(1_000);
    }
}

$tcp->close();
$udp->close();
```

If you have many servers and want them in the same `select()`, the cleanest path is a custom outer loop that pulls the underlying handles from `getSocket()` and runs a single `socket_select` / `stream_select` itself, then dispatches via per-server `tick(waitSeconds: 0.0)` calls. The package does not ship a multiplexer — see [Recipe Custom Channel](Recipe-Custom-Channel.md) for the shape such an extension takes.

## Testing with `tick()`

`tick()` is the seam that makes deterministic integration tests easy:

```php
$server = new TcpServer('127.0.0.1', $port);
$server->listen();

$client = new TcpClient('127.0.0.1', $port);
$client->connect();

$server->tick(fn () => null, 0.2);   // accept the queued client
self::assertCount(1, $server->getClients());

$client->write('ping');

$received = null;
$server->tick(function ($srv, $conn) use (&$received) {
    $received = $conn->read(1024);
}, 0.2);

self::assertSame('ping', $received);
```

No `pcntl_fork`, no threads, no flakiness. See [Testing Strategy](Testing-Strategy.md) for the full set of patterns the package's own test suite uses.

## See also

- **[Server Lifecycle](Server-Lifecycle.md)** — what state `listen` / `stop` / `close` actually mutate.
- **[Testing Strategy](Testing-Strategy.md)** — the package's own use of `tick()` in tests.
