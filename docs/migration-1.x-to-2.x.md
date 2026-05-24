# Migrating from 1.x to 2.x

2.x is a clean break. The 1.x server loop had race conditions, lost
inbound data through its liveness check, and stored transport state on
a `static` property that leaked between instances. Fixing those needed
API changes, so 2.x ships with a new shape rather than a back-compat
shim.

This guide walks the renames and behaviour changes you'll hit when
upgrading. The list is short — the public surface stays small.

## Requirements

| Aspect | 1.x | 2.x |
| --- | --- | --- |
| PHP | `>=7.4` | `^8.1` |
| Extensions | `ext-sockets` | `ext-sockets`, `ext-openssl` |
| Tested PHP versions | n/a (no CI) | 8.1, 8.2, 8.3 |

## Factory

```diff
- use InitPHP\Socket\Socket;
+ use InitPHP\Socket\Socket;
+ use InitPHP\Socket\Enum\Transport;

- $server = Socket::server(Socket::TCP, '127.0.0.1', 8080);
+ $server = Socket::server(Transport::TCP, '127.0.0.1', 8080);
```

The integer constants (`Socket::TCP`, `Socket::UDP`, `Socket::TLS`,
`Socket::SSL`) are gone. The `Transport` enum takes their place.

The `$argument` mystery parameter (whose meaning depended on transport)
is now two explicit named parameters:

```diff
- Socket::server(Socket::TCP, '127.0.0.1', 8080, 'v4');
+ Socket::server(Transport::TCP, '127.0.0.1', 8080, Domain::V4);

- Socket::server(Socket::TLS, '127.0.0.1', 8443, 5.0);
+ Socket::server(Transport::TLS, '127.0.0.1', 8443, timeout: 5.0);
```

## Server method renames

| 1.x | 2.x | Notes |
| --- | --- | --- |
| `connection()` | `listen()` | More accurate — it only binds + listens. Accept now happens inside `live()` / `tick()`. |
| `disconnect()` | `close()` | Tears down every client and the listening socket. |
| `live(callable $cb, int $usleep = 100000)` | `live(callable $cb, float $idleSeconds = 0.05)` | Same idea, now expressed in seconds. |
| `wait(int\|float $seconds)` | `wait(float $seconds)` | Sub-second precision via a single float. |
| `clientRegister($id, $conn)` | `register($id, $conn)` | Now part of the interface. |
| `broadcast($message, $clients = null)` | `broadcast(string $message, int\|string\|array\|null $ids = null)` | Always returns `bool`. Per-id targeting unchanged. |

New on `SocketServerInterface`:

- `tick(callable $cb, float $waitSeconds = 0.0): int` — single-iteration
  accept/dispatch step. Use this to embed the server in your own
  event loop or to drive it deterministically in tests.
- `stop(): void` — cooperatively exit the `live()` loop.
- `isRunning(): bool` — exposes the loop flag.

## ServerClient → ServerConnection

The per-accepted-connection class has been renamed and rebuilt:

| 1.x: `Server\ServerClient` | 2.x: `Server\ServerConnection` |
| --- | --- |
| `push(string $msg)` | `write(string $data): ?int` |
| `read(int $len, ?int $type = null)` | `read(int $len = 1024): ?string` |
| `close(): bool` | `close(): bool` |
| `isDisconnected(): bool` (consumed data!) | `isAlive(): bool` (non-destructive) |
| `getSocket()` returns mixed | `getSocket(): mixed`, plus `getChannel(): ChannelInterface` |
| `__setSocket()` magic | gone — channels are constructed normally |
| `static $credentials` shared state | gone — every connection owns its own `Channel` |

The most important behavioural change: **`isAlive()` does not read
data off the wire.** If you were depending on `isDisconnected()` to
both check liveness and consume the next line, you need to call
`read()` explicitly.

## Client method renames

| 1.x | 2.x |
| --- | --- |
| `connection()` | `connect()` |
| `disconnect()` | `disconnect()` (unchanged) |
| `read()` / `write()` | `read()` / `write()` (unchanged shape; return `null` instead of `false`) |

## Exceptions

All exceptions now implement a common `SocketExceptionInterface`. The
behavioural changes:

- `SocketException` now extends `\RuntimeException` (was `\Exception`).
- `SocketConnectionException` and `SocketListenException` extend
  `SocketException` (were `\Exception`).
- A single catch covers the package:

```diff
- try { /* ... */ } catch (\InitPHP\Socket\Exception\SocketException $e) { /* ... */ }
+ try { /* ... */ } catch (\InitPHP\Socket\Exception\SocketExceptionInterface $e) { /* ... */ }
```

## Removed traits and base classes

The `Common/` namespace is gone. If you were extending or composing
`BaseClient`, `BaseServer`, `BaseCommon`, `ServerTrait`,
`StreamClientTrait` or `StreamServerTrait`, switch to the new
abstracts:

| 1.x | 2.x |
| --- | --- |
| `Common\BaseClient` | `Client\AbstractClient` |
| `Common\BaseServer` | `Server\AbstractServer` |
| `Common\StreamClientTrait` | `Client\AbstractStreamClient` |
| `Common\StreamServerTrait` | `Server\AbstractStreamServer` |
| `Common\BaseCommon` / `Common\ServerTrait` | merged into the abstracts |

## Quick migration checklist

1. Bump `php` to `^8.1` in your project's `composer.json`.
2. Replace every `Socket::TCP` / `Socket::UDP` / `Socket::TLS` /
   `Socket::SSL` reference with the `Transport` enum case.
3. Replace `connection()` → `listen()` / `connect()`.
4. Replace `disconnect()` → `close()` on servers (clients keep it).
5. Rename `ServerClient` references to `ServerConnection` and
   `push()` to `write()`.
6. Audit every call to the old `isDisconnected()` — replace with
   `isAlive()` and read data explicitly.
7. If you have your own subclasses, point them at the new abstract
   parents under `Server/` / `Client/`.
8. Catch `SocketExceptionInterface` instead of (or in addition to)
   `SocketException`.
