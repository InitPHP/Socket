# Server Lifecycle

A server moves through four states. The transitions are explicit — the package never opens a network socket implicitly.

```
constructed ──listen()──► listening ──live()/tick()──► running ──stop()──► listening
                                                                 ──close()──► closed
```

## 1. Construct

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\{Transport, Domain};

$server = Socket::server(Transport::TCP, '127.0.0.1', 9000, Domain::V4);
```

At this point:

- No network call has been made.
- The host / port arguments are validated synchronously (empty host, port out of 1‒65535 → `SocketInvalidArgumentException`).
- For TLS / SSL the optional `$timeout` argument is stored for the handshake and per-client stream timeout.

## 2. Listen

```php
$server->listen();
```

- TCP: `socket_create` → `socket_bind` → `socket_listen` (with `backlog()` if configured).
- UDP: `socket_create` → `socket_bind` (no listen for datagrams).
- TLS / SSL: `stream_socket_server` with the configured SSL context, blocking mode preserved so the handshake during `accept` has room within its timeout.

After `listen()`:

- `getSocket()` returns the live listening handle.
- `getClients()` returns `[]` — accept happens inside `live()` / `tick()`, never inside `listen()`.

Calling `listen()` a second time **throws** `SocketException`. Re-bind a closed server by constructing a fresh instance.

```php
$server->listen();
$server->listen();   // ❌ SocketException("Server is already listening.")
```

## 3. Run

Two flavours: the all-in-one loop, or one iteration at a time.

### `live()` — managed loop

```php
$server->live(function ($srv, $conn) {
    $payload = $conn->read(1024);
    if ($payload === null) {
        return;
    }
    $conn->write("echo: {$payload}");
}, idleSeconds: 0.05);
```

- Sets `$running = true`.
- Calls `tick()` in a loop, passing the same `$idleSeconds` as the `select()` timeout.
- Exits when something inside the loop calls `$srv->stop()` (or the process is signalled).

### `tick()` — single iteration

```php
$events = $server->tick(callback, waitSeconds: 0.0);
```

- Runs one `socket_select` / `stream_select` with the supplied timeout.
- Accepts at most one pending client and services every readable existing connection.
- Returns the number of events handled (0 if no readiness was detected).

`tick()` is the integration seam for [Event Loop Integration](Event-Loop-Integration.md) and for [deterministic tests](Testing-Strategy.md).

### What happens per readable resource

- **Listening socket** → `accept()` a new peer, wrap it in a `Channel`, register it. No callback fires.
- **Existing client** → check `isAlive()`; if dead, close + evict; otherwise call the user callback with `($server, $connection)`.

UDP differs: there is no `accept()`. Each `tick()` does one `socket_recvfrom`, looks up (or creates) the `UdpChannel` for the source peer, pushes the datagram into its buffer, and invokes the callback.

## 4. Stop / Close

`stop()` flips the `$running` flag — the next `tick()` returns and `live()`'s loop exits. It does **not** close any sockets:

```php
$server->live(function ($srv, $conn) {
    if ($conn->read() === 'shutdown') {
        $srv->stop();   // exits live() — sockets remain open
    }
});
$server->close();       // tear-down
```

`close()` is the destructor: every client connection has `close()` called on it, the registries are emptied, the listening socket is released. It is idempotent — call it as many times as you like, even before `listen()`.

```php
$server->close();   // ✓ safe before listen()
$server->close();   // ✓ safe to call twice
```

After `close()`, `getSocket()` returns `null`. The instance is "done"; create a new one to listen again.

## Identity and broadcast

While the loop is running, every connection has a numeric internal key (auto-incremented per accept). You can attach an addressable id with `register()`:

```php
$server->live(function ($srv, $conn) {
    $line = trim((string) $conn->read());
    if (preg_match('/^HELLO (\w+)$/', $line, $m)) {
        $srv->register($m[1], $conn);
        $conn->write("welcome, {$m[1]}\n");
    } elseif (preg_match('/^TO (\w+) (.+)$/', $line, $m)) {
        $srv->broadcast($m[2], $m[1]);            // single id
    } else {
        $srv->broadcast($line);                    // everybody
    }
});
```

See [Recipe Chat Server](Recipe-Chat-Server.md) for the complete walk-through.

## Method matrix

| Method | Before `listen()` | After `listen()` | After `close()` |
|---|---|---|---|
| `listen()` | binds | **throws** | binds again is **not** allowed — make a new instance |
| `close()` | no-op (returns `true`) | tears down | no-op (returns `true`) |
| `live()` | **throws** | runs the loop | **throws** |
| `tick()` | **throws** | one iteration | **throws** |
| `stop()` | no-op | flips `$running` | no-op |
| `broadcast()` | no-op (no clients) | dispatches | no-op |
| `register()` | returns `false` (no clients) | maps id to client | returns `false` |
| `getClients()` | `[]` | live map | `[]` |
| `getSocket()` | `null` | listening handle | `null` |
| `getHost()` / `getPort()` | configured value | configured value | configured value |
| `wait(seconds)` | sleeps | sleeps | sleeps |

## See also

- **[Client Lifecycle](Client-Lifecycle.md)** — the symmetric model for the connect side.
- **[Event Loop Integration](Event-Loop-Integration.md)** — drive `tick()` from your own scheduler.
- **[Transport TCP](Transport-TCP.md)** / **[Transport UDP](Transport-UDP.md)** / **[Transport TLS](Transport-TLS.md)** / **[Transport SSL](Transport-SSL.md)** — transport-specific quirks of `listen()`.
