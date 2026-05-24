# Architecture

The 2.x line was designed around three goals: kill the shared-state bugs
of the 1.x release, isolate transport-specific I/O behind a strategy
interface, and stay friendly to host event loops. This page is the map
of how the pieces fit.

## Layers at a glance

```
┌─────────────────────────────────────────────────────────────┐
│  Socket factory                                              │
│  (Socket::server / Socket::client)                           │
└─────────────────────────────────────────────────────────────┘
                          │
        ┌─────────────────┴─────────────────┐
        ▼                                   ▼
┌────────────────────┐               ┌────────────────────┐
│  SocketServerInter │               │  SocketClientInter │
│       face         │               │       face         │
│  ─ AbstractServer  │               │  ─ AbstractClient  │
│   ├─ TCP / UDP     │               │   ├─ TCP / UDP     │
│   └─ AbstractStrea │               │   └─ AbstractStrea │
│      mServer       │               │      mClient       │
│       ├─ TLS       │               │       ├─ TLS       │
│       └─ SSL       │               │       └─ SSL       │
└────────────────────┘               └────────────────────┘
        │                                   │
        ▼                                   │
┌────────────────────┐                      │
│  ServerConnection  │   uses               │
│  (per accepted     │◀─────────────────────┘ (clients hold the
│   client)          │                         resource directly)
└────────────────────┘
        │
        ▼
┌────────────────────────────────────────┐
│  ChannelInterface                       │
│  ├─ TcpChannel    (ext-sockets)         │
│  ├─ UdpChannel    (ext-sockets, buffer) │
│  └─ StreamChannel (ext-openssl streams) │
└────────────────────────────────────────┘
```

## Why `Channel`?

A 1.x `ServerClient` carried a `switch ($type)` ladder in `push()`,
`read()`, `close()` and `isDisconnected()`. Adding a new transport meant
editing the class. The 2.x split moves transport-specific I/O into
dedicated `Channel` implementations:

- **`TcpChannel`** uses `socket_recv` / `socket_write` and detects peer
  close non-destructively with `MSG_PEEK | MSG_DONTWAIT`.
- **`StreamChannel`** uses `fread` / `fwrite` and asks `feof()` whether
  the peer is gone — never consuming data to find out.
- **`UdpChannel`** binds an identity (`peerHost:peerPort`) to the
  server's listening socket. The server routes inbound datagrams into
  the channel's local buffer via `feed()`; reads drain the buffer.
  Writes use `socket_sendto` directly.

`ServerConnection` just holds an id and forwards calls to its channel.
That keeps the per-connection object honest about its scope — identity
plus delegation — and makes broadcasting / id mapping trivial to test
with a fake channel.

## The server loop

The accept/dispatch flow is broken into two layers:

1. **`live(callable, float)`** — the long-running loop. It sets
   `$running = true` and repeatedly calls `tick()` until `stop()` is
   invoked.
2. **`tick(callable, float)`** — a single iteration. It runs the
   transport-specific `select()`, accepts new connections, services
   readable existing ones, and returns the number of events handled.

The split is deliberate. `tick()` is the integration seam: drop the
package into a host event loop and call `tick(waitSeconds: 0)` whenever
your scheduler picks our server. Tests use the same seam to drive the
server deterministically without forking processes.

## Why select-driven?

The 1.x `live()` called blocking `socket_accept()` on every iteration,
so existing clients were ignored until a new one connected. The 2.x
loop builds a read set of `[listenSocket, ...activeClientSockets]`,
hands it to `socket_select` / `stream_select` with the caller-supplied
timeout, then services exactly the resources the kernel said are ready.

Non-blocking accept is set after a `select()` reports a pending client,
not as a permanent property of the listening socket — `stream_socket_server`
behaves differently than `socket_create` here, and the stream-server
implementation keeps the listen socket blocking so `stream_socket_accept`
has room to complete the TLS handshake within its timeout.

## Liveness, no data loss

`isAlive()` never touches the application payload:

- **TCP** — `socket_recv($sock, $tmp, 1, MSG_PEEK | MSG_DONTWAIT)`. A
  zero return value means the peer closed; `EAGAIN`/`EWOULDBLOCK` means
  alive-but-quiet.
- **Stream (TLS / SSL)** — `feof()` after confirming the resource is
  still valid.
- **UDP** — an in-process `alive` flag; UDP has no connection state,
  so dead peers are detected by application-level TTL.

## Exceptions

Every exception thrown by the package implements
`SocketExceptionInterface`, so callers can do:

```php
try {
    $server->listen();
} catch (SocketExceptionInterface $e) {
    // bind failed, listen failed, invalid argument — all caught here
}
```

The hierarchy:

```
SocketExceptionInterface
├─ SocketException (RuntimeException)
│  ├─ SocketConnectionException
│  └─ SocketListenException
└─ SocketInvalidArgumentException (InvalidArgumentException)
```

The split keeps the "your input is wrong" path on
`InvalidArgumentException` semantics while still being catchable
together with runtime failures via the marker interface.
