# Connection and Channel

When a server accepts a new peer, it wraps the underlying handle in a `Channel` (transport strategy) and the channel in a `ServerConnection` (identity + delegation). This page documents both.

## `SocketConnectionInterface`

What every accepted-peer object exposes to the user callback:

```php
namespace InitPHP\Socket\Interfaces;

interface SocketConnectionInterface
{
    public function setId(int|string $id): static;
    public function getId(): int|string|null;

    public function read(int $length = 1024): ?string;
    public function write(string $data): ?int;
    public function close(): bool;
    public function isAlive(): bool;

    public function getSocket(): mixed;
    public function getChannel(): ChannelInterface;
}
```

### Identity

`setId()` and `getId()` exist for one purpose — addressable broadcasts. Until the application sets an id, `getId()` returns `null`:

```php
$server->live(function ($srv, $conn) {
    $line = trim((string) $conn->read());
    if (preg_match('/^REGISTER (\w+)$/', $line, $m)) {
        $srv->register($m[1], $conn);     // calls $conn->setId($m[1]) internally
    }
});

// Later, broadcast to one id:
$server->broadcast('hello admin', 'admin');
```

The id can be either `int` or `string`. It is **not** unique by design — register the same id on two connections and broadcast reaches both, in registration order.

### Reading and writing

`read()` is non-destructive in failure modes — it returns `null` for "no data right now / EOF / underlying error", never raises. `write()` returns the number of bytes actually written, or `null` on failure.

> Both methods delegate to the channel. The connection adds no buffering of its own; whatever the channel returns is what you get.

### Liveness

`isAlive()` **never consumes data**. The 1.x package's `isDisconnected()` did, and that hid every callback's payload in a 1-byte read on every loop iteration. The 2.x contract: `isAlive()` only peeks / inspects state.

The server's accept/dispatch loop calls `isAlive()` after every `select()` returns the connection as readable. If the answer is `false`, the connection is closed and evicted before your callback runs.

### Native handle

`getSocket()` returns whatever the underlying transport uses:

| Transport | Returns |
|---|---|
| TCP | `\Socket` (PHP 8 ext-sockets object) |
| UDP | `\Socket` (the server's listening socket, shared by every UdpChannel) |
| TLS / SSL | `resource` (a stream) |

For long-lived custom logic, prefer `getChannel()` — it gives you the strategy object directly and is typed.

## `ChannelInterface`

The transport strategy that does the actual I/O:

```php
namespace InitPHP\Socket\Interfaces;

interface ChannelInterface
{
    public function read(int $length = 1024, ?int $flag = null): ?string;
    public function write(string $data): ?int;
    public function close(): bool;
    public function isAlive(): bool;
    public function getResource(): mixed;
}
```

`ChannelInterface` is the seam if you ever need to add a new transport — see [Recipe Custom Channel](Recipe-Custom-Channel.md) for a worked example.

## The three built-in channels

### `Channel\TcpChannel`

Backed by `ext-sockets`:

- `read()` calls `socket_read` with `PHP_BINARY_READ` by default; pass `PHP_NORMAL_READ` to read line-by-line.
- `write()` calls `socket_write`.
- `isAlive()` uses `socket_recv($s, $tmp, 1, MSG_PEEK | MSG_DONTWAIT)`. A 0 result is "peer closed"; `EAGAIN` / `EWOULDBLOCK` is "no data right now, still alive".
- `close()` calls `socket_close` and is idempotent.

### `Channel\StreamChannel`

Backed by stream resources (TLS / SSL servers):

- `read()` calls `fread`; refuses lengths < 1 by returning `null`.
- `write()` calls `fwrite`.
- `isAlive()` is `is_resource() && !feof()`.
- `close()` calls `fclose` and is idempotent.

### `Channel\UdpChannel`

The most unusual of the three because UDP has no per-peer socket. A `UdpChannel`:

- Holds the **server's listening socket** (shared by every channel) plus an immutable `(peerHost, peerPort)` identity.
- Carries an internal `string $buffer`. The server calls `feed(string $data)` to push inbound datagrams into the right channel.
- `read()` drains the buffer up to `$length` bytes. There is no kernel-level read here — the server already did the `recvfrom`.
- `write()` calls `socket_sendto` on the listening socket with the bound peer.
- `close()` flips an internal alive flag and clears the buffer. It does **not** close the listening socket — that belongs to the server.

Extra methods on `UdpChannel`:

```php
$channel->feed(string $data): void;   // server-side: push inbound bytes
$channel->getPeerHost(): string;
$channel->getPeerPort(): int;
$channel->peerKey(): string;          // 'host:port' — used as the server's map key
```

The server keeps a `peerKey() → internalKey` index so returning peers reuse their existing channel rather than spawning a new one per packet.

## Building a connection by hand

You normally never construct a `ServerConnection` yourself — the server does it after each `accept` / `recvfrom`. But the constructor is public and trivially mockable, which is what makes the broadcast / registry logic so easy to unit-test:

```php
use InitPHP\Socket\Server\ServerConnection;
use InitPHP\Socket\Channel\StreamChannel;

$stream = fopen('php://memory', 'r+');
$conn   = new ServerConnection(new StreamChannel($stream));
$conn->setId('admin');
$conn->write("hello\n");
rewind($stream);
echo fread($stream, 1024);   // hello\n
```

This pattern is exactly how the package's own [Testing Strategy](Testing-Strategy.md) exercises the abstract server logic without binding any real ports.

## See also

- **[Architecture](Architecture.md)** — where connections and channels sit in the layered model.
- **[Recipe Custom Channel](Recipe-Custom-Channel.md)** — implementing `ChannelInterface` from scratch.
- **[Testing Strategy](Testing-Strategy.md)** — using fake channels in tests.
