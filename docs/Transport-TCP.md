# Transport — TCP

Stream-oriented, reliable, in-order delivery. The default and most-tested transport in the package, backed by `ext-sockets`.

## When to choose TCP

- The protocol expects an ordered byte stream (HTTP-like, line-based, custom framing on top of length-prefixed records).
- You need confirmation that bytes either arrived or the peer closed.
- Encryption is **not** required at this layer. (Use [Transport TLS](Transport-TLS.md) for that.)

## Server

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\{Transport, Domain};

$server = Socket::server(Transport::TCP, '127.0.0.1', 9000, Domain::V4);
$server->backlog(16);     // optional, default 8
$server->listen();
$server->live(function ($srv, $conn) {
    $payload = $conn->read(1024);
    if ($payload !== null) {
        $conn->write("echo: {$payload}");
    }
});
```

Direct construction is also supported if you do not want the factory:

```php
use InitPHP\Socket\Server\TCP as TcpServer;

$server = (new TcpServer('127.0.0.1', 9000, Domain::V4))->backlog(16);
```

### Configuration

| Method | Default | Effect |
|---|---|---|
| `backlog(int $n)` | `8` | OS listen backlog for pending connections waiting to be accepted. |

`backlog()` is chainable and validates that `$n >= 1` (throws `SocketInvalidArgumentException` otherwise).

### Inside `tick()`

For TCP the per-iteration flow is:

1. Build `[listenSocket, ...activeClients]` and hand it to `socket_select` with the supplied timeout.
2. For every readable resource:
   - **Listening** → `socket_accept`, wrap in `TcpChannel`, set non-blocking, register.
   - **Existing client** → `isAlive()` via `MSG_PEEK | MSG_DONTWAIT`. Dead → close + evict. Alive → invoke the callback.

`socket_select` failures with `SOCKET_EINTR` are treated as benign (just retry on the next iteration). Other failures throw `SocketException`.

## Client

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\{Transport, Domain};

$client = Socket::client(Transport::TCP, '127.0.0.1', 9000, Domain::V4);
$client->connect();

$client->write("PING\r\n");
echo $client->read(1024, PHP_NORMAL_READ);   // line-oriented read

$client->disconnect();
```

### Read modes

`read(int $length = 1024, int $type = PHP_BINARY_READ)` matches PHP's `socket_read`:

| Constant | Behaviour |
|---|---|
| `PHP_BINARY_READ` (default) | Read up to `$length` raw bytes. |
| `PHP_NORMAL_READ` | Read until `\n` or `\r` is observed; the delimiter is **included** in the returned string. |

## Address families

```php
Socket::server(Transport::TCP, '127.0.0.1', 9000, Domain::V4);   // IPv4 (default)
Socket::server(Transport::TCP, '::1',       9000, Domain::V6);   // IPv6
Socket::server(Transport::TCP, '/tmp/initphp.sock', 1, Domain::UNIX);  // UDS
```

For `Domain::UNIX`, the constructor still demands `port >= 1`. The port is ignored by the kernel for UDS — pass any non-zero placeholder. A real-world UDS pattern is:

```php
$server = Socket::server(Transport::TCP, '/var/run/myapp.sock', 1, Domain::UNIX);
$server->listen();
chmod('/var/run/myapp.sock', 0660);
```

The `Domain` enum maps to `AF_INET` / `AF_INET6` / `AF_UNIX` internally; see [Enums](Enums.md).

## Common gotchas

### Half-closes

TCP supports a half-close where the peer stops sending but still receives. The package treats any zero-byte `MSG_PEEK` as full close — there is no `shutdown()` exposed. If you need half-close semantics, call `socket_shutdown` on `$conn->getSocket()` yourself.

### Backlog vs accept rate

`backlog()` controls the kernel's pending-accept queue. Under burst load with a slow `tick()` cadence, raising the backlog buys you time. If clients see `ECONNREFUSED` under load, raise it.

### `EINTR` from signal-heavy processes

If you install signal handlers, `socket_select` will return `false` with `socket_last_error() === SOCKET_EINTR` whenever a signal fires during the call. The package treats this as benign and continues — no work is lost.

## Exception flow

| Error | Exception |
|---|---|
| `socket_create` fails | `SocketException` |
| `socket_bind` fails (port in use, perms, …) | `SocketException` |
| `socket_listen` fails | `SocketListenException` |
| `socket_accept` errors (non-EINTR) | `SocketConnectionException` |
| `socket_select` errors (non-EINTR) | `SocketException` |
| `socket_connect` fails | `SocketConnectionException` |
| `backlog($n < 1)` | `SocketInvalidArgumentException` |
| `connect()` twice on same client | `SocketException` |
| `listen()` twice on same server | `SocketException` |
| `tick()` before `listen()` | `SocketException` |

Every one of these implements `SocketExceptionInterface` so a single catch can guard the whole transport.

## See also

- **[Server Lifecycle](Server-Lifecycle.md)** / **[Client Lifecycle](Client-Lifecycle.md)** — what the methods do across states.
- **[Connection and Channel](Connection-and-Channel.md)** — what `$conn->read()` actually calls.
- **[Recipe Echo Server](Recipe-Echo-Server.md)** — a runnable end-to-end example.
- **[Recipe Chat Server](Recipe-Chat-Server.md)** — `register()` + `broadcast()` patterns.
