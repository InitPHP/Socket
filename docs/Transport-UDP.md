# Transport — UDP

Datagram-oriented, connectionless. The package makes UDP look more connection-shaped without lying about the wire protocol.

## When to choose UDP

- You exchange small, idempotent messages (DNS queries, telemetry, NTP-style time sync).
- Loss is acceptable, or your protocol handles retransmits explicitly (QUIC-style, audio / video streaming).
- Latency matters more than reliability.

UDP **never** retransmits, **never** orders, and the OS will silently drop datagrams if the receive buffer fills.

## Server model — how "connection" works here

A single listening socket fields datagrams from every peer. The package demultiplexes by source address:

1. `tick()` does one `socket_recvfrom`, capturing the source `(host, port)`.
2. If that peer has a tracked `UdpChannel`, the channel receives the bytes via `feed()`.
3. Otherwise a fresh `UdpChannel` + `ServerConnection` is built for the peer and registered.
4. The callback fires with `($server, $connection)`.

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::UDP, '0.0.0.0', 9000);
$server->listen();

$server->live(function ($srv, $conn) {
    $payload = $conn->read(65535);
    if ($payload !== null) {
        $conn->write("pong: {$payload}");
    }
});
```

`$conn->read()` drains the channel's local buffer — it never goes back to the kernel. `$conn->write()` does a `socket_sendto` on the listening socket targeting the channel's bound peer.

## Datagram sizes

`UdpChannel::MAX_DATAGRAM = 65535` is the internal read buffer for `recvfrom`. The IPv4 ceiling for a UDP payload is **65 507 bytes** (UDP header + IP header + length field). For real-world reliability stay well under MTU (≈1472 bytes on Ethernet) to avoid IP fragmentation.

## No backlog, no accept

UDP has no listen queue and no `accept()`. `listen()` does `socket_create` + `socket_bind` only; the loop never calls accept.

## Client

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::UDP, '127.0.0.1', 9000);
$client->connect();

$client->write('ping');
echo $client->read(65535);

$client->disconnect();
```

`connect()` on a UDP client calls `socket_connect`, which on UDP just locks the local socket to a single peer at the kernel level. After that, you can `socket_send` / `socket_recv` as if it were a stream socket — that is exactly what `write()` / `read()` do internally. No datagram round-trip happens during `connect()`.

### Flags

Both `read()` and `write()` accept an optional `int $flags`:

```php
$client->read(1024, MSG_PEEK);            // inspect without consuming
$client->write('hello', MSG_DONTROUTE);
```

Recognised bitmask values mirror PHP's wrapper around `recv` / `send`:

| Direction | Flags |
|---|---|
| `read()` | `MSG_OOB`, `MSG_PEEK`, `MSG_WAITALL`, `MSG_DONTWAIT` |
| `write()` | `MSG_OOB`, `MSG_EOR`, `MSG_EOF`, `MSG_DONTROUTE` |

Leave `$flags` at the default `0` unless you specifically need one of these.

## Liveness

UDP has no connection state. `UdpChannel::isAlive()` returns `true` until you call `close()`. To evict silent peers, you need application-level TTL — track `lastSeen` per channel and call `$conn->close()` when it ages out:

```php
$lastSeen = new SplObjectStorage();
$server->live(function ($srv, $conn) use ($lastSeen) {
    $payload = $conn->read(65535);
    if ($payload !== null) {
        $lastSeen[$conn] = microtime(true);
    }
    $now = microtime(true);
    foreach ($srv->getClients() as $c) {
        if (($lastSeen[$c] ?? 0) + 60.0 < $now) {
            $c->close();
        }
    }
});
```

The next `tick()` after `close()` will evict the dead channel from `getClients()`.

## Broadcast across known peers

`broadcast()` iterates the channels the server has heard from. It is **not** the IP broadcast address — it is application-level fan-out. UDP has no peer-discovery, so a peer exists only after speaking first.

```php
$server->broadcast('announce');                 // every known peer
$server->broadcast('hello-admin', 'admin');     // by registered id
$server->broadcast('hi-vips', ['admin', 'vip']);
```

## Common gotchas

### "Why did my datagram vanish?"

- Receive buffer full → kernel dropped it.
- Peer wasn't listening yet → silent loss (no handshake).
- Payload bigger than MTU and `Don't Fragment` was set → ICMP error you may never see.

UDP is silent in these cases by design. If you need confirmation, build acks into the protocol.

### "Why are multiple datagrams concatenated in one `read()`?"

They are not — but the package's `UdpChannel` buffer holds bytes from every datagram the kernel hands over between callbacks. If two datagrams arrive between two `tick()` calls, both are appended to the same internal buffer, and your next `$conn->read(1024)` returns up to 1024 bytes from the front. Either size your reads correctly or split datagrams via length-prefix framing in the application protocol.

## Exception flow

| Error | Exception |
|---|---|
| `socket_create` fails | `SocketException` |
| `socket_bind` fails | `SocketException` |
| `socket_select` errors (non-EINTR) | `SocketException` |
| `socket_connect` fails (UDP client) | `SocketConnectionException` |
| `listen()` twice | `SocketException` |
| `connect()` twice (client) | `SocketException` |
| `tick()` before `listen()` | `SocketException` |

## See also

- **[Server Lifecycle](Server-Lifecycle.md)** / **[Client Lifecycle](Client-Lifecycle.md)**
- **[Connection and Channel](Connection-and-Channel.md)** — `UdpChannel` specifics.
- **[Recipe Broadcast](Recipe-Broadcast.md)** — UDP fan-out pattern.
