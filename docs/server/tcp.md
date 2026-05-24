# TCP server

Backed by the `sockets` extension (`socket_create` / `socket_listen` /
`socket_accept`). Suitable for any stream-oriented binary or text
protocol.

## Quick reference

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\{Transport, Domain};

$server = Socket::server(
    Transport::TCP,
    '127.0.0.1',
    8080,
    domain: Domain::V4,   // V4 (default), V6 or UNIX
);

$server->listen();
$server->live(function ($srv, $conn) {
    $payload = $conn->read(1024);
    if ($payload !== null) {
        $conn->write("ack:{$payload}");
    }
});
```

## Options

| Method | Default | Notes |
| --- | --- | --- |
| `backlog(int $n)` | `8` | OS listen backlog for unaccepted connections. |

## Domain selection

| `Domain` case | Address family |
| --- | --- |
| `V4` | `AF_INET` (default) |
| `V6` | `AF_INET6` |
| `UNIX` | `AF_UNIX` (UDS path goes in the `host` argument) |

UDS example:

```php
$server = Socket::server(Transport::TCP, '/tmp/initphp.sock', 0, Domain::UNIX);
```

> **Note:** for `Domain::UNIX`, the `port` argument is ignored by the
> kernel but still has to satisfy the constructor's `>0` check; pass
> any non-zero placeholder (or use a real port if you ever expose the
> service via TCP too).

## Lifecycle in code

```php
$server = Socket::server(Transport::TCP, '127.0.0.1', 8080);

// throws SocketListenException if bind/listen fails
$server->listen();

try {
    $server->live(function ($srv, $conn) {
        // ...
    });
} finally {
    $server->close();      // tears down every client + the listen socket
}
```

## Targeted broadcasting

```php
$server->live(function ($srv, $conn) {
    $payload = $conn->read();
    if ($payload === null) return;

    if (\preg_match('/^REGISTER\s+(.+)$/', $payload, $m)) {
        $srv->register($m[1], $conn);
        return;
    }
    if (\preg_match('/^DM\s+(\S+)\s+(.+)$/', $payload, $m)) {
        $srv->broadcast($m[2], $m[1]);     // single id
        return;
    }
    $srv->broadcast($payload);              // every client
});
```

`broadcast()` accepts:

- `null` — every alive client
- `int|string` — the connection previously registered under that id
- `int[]|string[]` — multiple ids at once

## Driving the loop yourself

`live()` is just `while (running) tick()`. If you have your own loop,
use `tick()` directly:

```php
$server->listen();
while ($app->isRunning()) {
    $events = $server->tick(function ($srv, $conn) {
        /* ... */
    }, waitSeconds: 0.0);

    if ($events === 0) {
        $app->yieldOnce();
    }
}
```
