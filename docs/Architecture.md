# Architecture

`initphp/socket` is layered so each part has one job and is replaceable in isolation. This page walks the layers top-down, then explains the design decisions behind the more interesting ones.

## Layers at a glance

```
┌──────────────────────────────────────────────────────────┐
│  Socket  (factory)                                        │
│  Socket::server(Transport, …) / Socket::client(Transport,…) │
└──────────────────────────────────────────────────────────┘
                          │
        ┌─────────────────┴─────────────────┐
        ▼                                   ▼
┌───────────────────────┐         ┌───────────────────────┐
│ SocketServerInterface │         │ SocketClientInterface │
│ ── AbstractServer     │         │ ── AbstractClient     │
│    ├ TCP              │         │    ├ TCP              │
│    ├ UDP              │         │    ├ UDP              │
│    └ AbstractStream   │         │    └ AbstractStream   │
│       Server          │         │       Client          │
│       ├ TLS           │         │       ├ TLS           │
│       └ SSL           │         │       └ SSL           │
└───────────────────────┘         └───────────────────────┘
        │                                   │
        ▼                                   │
┌───────────────────────┐                   │ (clients hold the
│ ServerConnection      │ ◀─────────────────┘  resource directly)
│ (per accepted client) │
└───────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────┐
│ ChannelInterface                                 │
│  ├ TcpChannel   — ext-sockets, MSG_PEEK liveness │
│  ├ UdpChannel   — ext-sockets, peer buffer       │
│  └ StreamChannel — fopen / fread / feof          │
└─────────────────────────────────────────────────┘
```

## The factory

`InitPHP\Socket\Socket` is the only public entry point. It takes a `Transport` enum case and dispatches to the right concrete class:

```php
public static function server(
    Transport $transport,
    string $host,
    int $port,
    ?Domain $domain = null,   // TCP/UDP only
    ?float $timeout = null,   // TLS/SSL only
): SocketServerInterface;
```

The factory does not bind, listen or connect. It returns a configured-but-passive instance — the caller decides when to drive the lifecycle. See [Server Lifecycle](Server-Lifecycle.md) and [Client Lifecycle](Client-Lifecycle.md).

## Servers

Every server implements `SocketServerInterface`:

| Method | What it does |
|---|---|
| `listen()` | Bind and start listening. Does **not** accept any client. |
| `live(cb, idle)` | Run the accept/dispatch loop until `stop()` is called. |
| `tick(cb, wait)` | One iteration of the loop. Returns event count. |
| `stop()` | Cooperatively exit `live()`. |
| `close()` | Tear down all clients + the listening socket. |
| `broadcast(msg, ids?)` | Fan out to all / by-id / by-list. |
| `register(id, conn)` | Map an addressable id to a connection. |
| `getClients()` | `array<int|string, SocketConnectionInterface>`. |
| `wait(seconds)` | Sub-second sleep helper. |

`AbstractServer` carries the registry / broadcast / wait helpers. `Server\TCP` and `Server\UDP` plug into `ext-sockets`. `AbstractStreamServer` plugs into the stream wrapper family and is the common parent of `Server\TLS` and `Server\SSL`.

## Clients

Symmetric to servers, every client implements `SocketClientInterface`:

| Method | What it does |
|---|---|
| `connect()` | Open the connection. |
| `disconnect()` | Close it. Idempotent. |
| `read(len)` | Receive up to `len` bytes. `null` on no data / failure. |
| `write(data)` | Send `data`. Returns bytes written or `null`. |

`Client\TCP` and `Client\UDP` use `ext-sockets` directly. `AbstractStreamClient` (parent of `Client\TLS` and `Client\SSL`) wraps `stream_socket_client` and additionally exposes `option()`, `timeout()`, `blocking()` and `crypto()` chainables.

## ServerConnection

When a server accepts a new peer, it wraps the underlying handle in a `Channel` and the channel in a `ServerConnection`. The connection is **only** identity plus delegation:

```php
final class ServerConnection implements SocketConnectionInterface
{
    private int|string|null $id = null;

    public function __construct(private readonly ChannelInterface $channel) {}

    public function read(int $length = 1024): ?string  { return $this->channel->read($length); }
    public function write(string $data): ?int          { return $this->channel->write($data); }
    public function close(): bool                       { return $this->channel->close(); }
    public function isAlive(): bool                     { return $this->channel->isAlive(); }
    public function getSocket(): mixed                  { return $this->channel->getResource(); }
    public function getChannel(): ChannelInterface     { return $this->channel; }
    public function setId(int|string $id): static       { /* … */ }
    public function getId(): int|string|null            { /* … */ }
}
```

This split keeps the per-connection object honest about its scope. Tests can replace the channel with a fake to exercise broadcasting / id mapping in isolation — see [Testing Strategy](Testing-Strategy.md).

## Channels (Strategy)

The 1.x release had a `switch ($transportType)` ladder inside `ServerClient::push()`, `read()`, `close()` and `isDisconnected()`. The 2.x split moves the transport-specific I/O into dedicated channels:

- **`TcpChannel`** uses `socket_recv` / `socket_write` and detects peer close non-destructively with `MSG_PEEK | MSG_DONTWAIT`.
- **`StreamChannel`** uses `fread` / `fwrite` and asks `feof()` whether the peer is gone — without consuming bytes.
- **`UdpChannel`** binds an identity (`peerHost:peerPort`) to the server's listening socket. The server routes inbound datagrams into the channel's local buffer via `feed()`; reads drain the buffer. Writes use `socket_sendto` directly.

Adding a new transport is "implement `ChannelInterface`", not "edit every `switch` in the package".

## The server loop

The accept/dispatch flow has two layers — `live()` is the long-running while loop, `tick()` is a single iteration:

```php
// AbstractServer::live (simplified)
public function live(callable $callback, float $idleSeconds = 0.05): void
{
    $this->running = true;
    while ($this->isRunning()) {
        $this->tick($callback, $idleSeconds);
    }
}
```

`tick()` is implemented per-server. It always follows the same shape:

1. Build the read set: `[listenSocket, ...activeClientSockets]`.
2. Hand it to `socket_select` / `stream_select` with the caller-supplied timeout.
3. For every resource the kernel reports readable:
   - If it is the listening socket → `accept()` a new client and wrap it in a channel.
   - If it is an existing client → check liveness, then invoke the callback.

UDP departs slightly: it has no `accept()`, so the loop is `recvfrom` → lookup-or-create the `UdpChannel` for that peer → feed the buffer → invoke the callback.

## Why `select()`-driven?

The 1.x `live()` called blocking `socket_accept()` on every iteration, so existing clients were ignored until a new one connected. The 2.x loop tells the kernel about every resource it cares about and then services only the ones that are actually ready. This makes the server responsive to data on existing connections while still accepting new ones.

## Liveness, no data loss

`isAlive()` never touches the application payload:

- **TCP** — `socket_recv($sock, $buf, 1, MSG_PEEK | MSG_DONTWAIT)`. A 0 result means peer closed; `EAGAIN` / `EWOULDBLOCK` means alive-but-quiet.
- **Stream (TLS / SSL)** — `feof()` after a `is_resource()` guard.
- **UDP** — an in-process `alive` flag; UDP has no connection state, so dead peers are detected by application-level TTL.

This was the worst 1.x bug: `isDisconnected()` read a line off the socket every iteration and **discarded it**, so the application callback never saw the payload it expected. 2.x's `isAlive()` is non-destructive by contract.

## Exceptions

Every exception in the package implements `SocketExceptionInterface`, so a single catch covers them all:

```
SocketExceptionInterface  (marker)
├─ SocketException                  (extends \RuntimeException)
│  ├─ SocketConnectionException
│  └─ SocketListenException
└─ SocketInvalidArgumentException   (extends \InvalidArgumentException)
```

See [Exceptions](Exceptions.md) for the full reference.

## Where to next

- **[Server Lifecycle](Server-Lifecycle.md)** — the public method contract per state.
- **[Connection and Channel](Connection-and-Channel.md)** — the per-accepted-client object and its strategy.
- **[Event Loop Integration](Event-Loop-Integration.md)** — how to plug `tick()` into an external loop.
