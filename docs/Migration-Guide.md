# Migration Guide — 1.x → 2.x

2.x is a clean break from 1.x. The previous server loop had race conditions, lost inbound data through its liveness check, and stored transport state on a `static` property that leaked between instances. Fixing those needed API changes, so 2.x ships with a new shape rather than a back-compat shim.

This guide is a step-by-step upgrade. The public surface stays small, so the work is mechanical.

## Requirements

| Aspect | 1.x | 2.x |
|---|---|---|
| PHP | `>=7.4` | `^8.1` in 2.x; `^8.2` from 3.0.0 |
| Required extensions | `ext-sockets` | `ext-sockets`, `ext-openssl` |
| CI tested versions | n/a | 8.2, 8.3, 8.4, 8.5, 8.6 |

## Factory

The integer constants are gone. The `Transport` enum takes their place, and the mystery `$argument` parameter is split into two explicit named parameters:

```diff
- use InitPHP\Socket\Socket;
+ use InitPHP\Socket\Socket;
+ use InitPHP\Socket\Enum\{Transport, Domain};

- $server = Socket::server(Socket::TCP, '127.0.0.1', 8080);
+ $server = Socket::server(Transport::TCP, '127.0.0.1', 8080);

- $server = Socket::server(Socket::TCP, '127.0.0.1', 8080, 'v6');
+ $server = Socket::server(Transport::TCP, '127.0.0.1', 8080, Domain::V6);

- $server = Socket::server(Socket::TLS, '127.0.0.1', 8443, 5.0);
+ $server = Socket::server(Transport::TLS, '127.0.0.1', 8443, timeout: 5.0);
```

## Server method renames

| 1.x | 2.x | Notes |
|---|---|---|
| `connection()` | `listen()` | More accurate — accept happens later. |
| `disconnect()` | `close()` | Same shape, more accurate name. |
| `live(callable, int $usleep = 100000)` | `live(callable, float $idleSeconds = 0.05)` | Same idea, now in seconds. |
| `wait(int\|float $seconds)` | `wait(float $seconds)` | Sub-second precision via one float. |
| `clientRegister($id, $conn)` | `register($id, $conn)` | Promoted to the interface. |
| `broadcast($message, $clients = null)` | `broadcast(string $message, int\|string\|array\|null $ids = null)` | Always returns `bool`. |

New on `SocketServerInterface`:

- `tick(callable, float = 0.0): int` — single-iteration accept/dispatch.
- `stop(): void` — cooperative exit from `live()`.
- `isRunning(): bool` — query the loop flag.

## `ServerClient` → `ServerConnection`

The per-accepted-connection class is renamed and rebuilt:

| 1.x: `Server\ServerClient` | 2.x: `Server\ServerConnection` |
|---|---|
| `push(string $msg)` | `write(string $data): ?int` |
| `read(int $len, ?int $type = null)` | `read(int $len = 1024): ?string` |
| `close(): bool` | `close(): bool` |
| `isDisconnected(): bool` (**consumed data!**) | `isAlive(): bool` (non-destructive) |
| `getSocket()` returns mixed | `getSocket(): mixed`, plus `getChannel(): ChannelInterface` |
| `__setSocket()` magic | gone — channels are constructed normally |
| `static $credentials` shared state | gone — every connection owns its own `Channel` |

The most important behavioural change: **`isAlive()` does not read data off the wire.** If your 1.x code relied on `isDisconnected()` to both check liveness **and** consume the next line, you now need to call `read()` explicitly.

```diff
- if ($conn->isDisconnected()) { continue; }
- // (data already silently read by isDisconnected and discarded)
+ if (!$conn->isAlive()) { continue; }
+ $line = $conn->read(1024);
+ if ($line !== null) {
+     // ... handle line ...
+ }
```

## Client method renames

| 1.x | 2.x |
|---|---|
| `connection()` | `connect()` |
| `disconnect()` | `disconnect()` (unchanged) |
| `read()` / `write()` | `read()` / `write()` (return `null` instead of `false` on failure) |

## Exceptions

The shape changed:

- `SocketException` now extends `\RuntimeException` (was `\Exception`).
- `SocketConnectionException` and `SocketListenException` extend `SocketException` (were `\Exception` directly).
- A new marker interface `SocketExceptionInterface` is implemented by every package exception, including `SocketInvalidArgumentException`.

```diff
- try { /* ... */ } catch (\InitPHP\Socket\Exception\SocketException $e) { /* ... */ }
+ try { /* ... */ } catch (\InitPHP\Socket\Exception\SocketExceptionInterface $e) { /* ... */ }
```

The 1.x `SocketInvalidArgumentException` was disjoint from `SocketException`. In 2.x the marker interface unifies them.

## Removed traits and base classes

The `Common/` namespace is gone. If you were extending or composing the 1.x base types, switch to the new abstracts:

| 1.x | 2.x |
|---|---|
| `Common\BaseClient` | `Client\AbstractClient` |
| `Common\BaseServer` | `Server\AbstractServer` |
| `Common\StreamClientTrait` | `Client\AbstractStreamClient` |
| `Common\StreamServerTrait` | `Server\AbstractStreamServer` |
| `Common\BaseCommon` / `Common\ServerTrait` | merged into the abstracts |

## Domain selection

The 1.x string-typed `$argument` is now the `Domain` enum:

```diff
- Socket::server(Socket::TCP, '127.0.0.1', 8080, 'v4');
+ Socket::server(Transport::TCP, '127.0.0.1', 8080, Domain::V4);

- Socket::server(Socket::UDP, '/tmp/x.sock', 0, 'unix');
+ Socket::server(Transport::UDP, '/tmp/x.sock', 1, Domain::UNIX);
```

For `Domain::UNIX`, the constructor still demands `port >= 1`. Pass any non-zero placeholder; the kernel ignores the port for UDS.

## Crypto methods

1.x used lower-case strings (`'tls'`, `'tlsv1.2'`, `'sslv23'`). 2.x uses the `CryptoMethod` enum:

```diff
- $server->crypto('tlsv1.2');
+ $server->crypto(\InitPHP\Socket\Enum\CryptoMethod::TLSv1_2);

- $server->crypto(null);
+ $server->crypto(null);   // still allowed — clears the override
```

`CryptoMethod::fromName('TLSv1.2')` parses a config-file string into an enum case if you can't hard-code the case.

## Quick migration checklist

1. Bump `php` to `^8.2` in your project's `composer.json`. Add `ext-openssl` if you don't already require it.
2. Replace every `Socket::TCP` / `UDP` / `TLS` / `SSL` reference with the matching `Transport` enum case.
3. Replace the third "argument" parameter on factory calls with `Domain::*` (TCP / UDP) or `timeout:` (TLS / SSL).
4. Rename `connection()` → `listen()` (server) or `connect()` (client).
5. Rename `disconnect()` → `close()` on servers (clients keep `disconnect()`).
6. Rename `ServerClient` references to `ServerConnection`, `push()` to `write()`.
7. Audit every call to `isDisconnected()` — switch to `isAlive()` and read data explicitly. **This is the most important step.**
8. Move 1.x `Common\*` base classes to the new abstracts under `Server\` / `Client\`.
9. Switch crypto string arguments to `CryptoMethod` enum cases.
10. Catch `SocketExceptionInterface` instead of (or in addition to) `SocketException` if you want a single catch site.

## See also

- **[Architecture](Architecture.md)** — the new layered design.
- **[Server Lifecycle](Server-Lifecycle.md)** — `listen` / `live` / `tick` / `close` semantics.
- **[Connection and Channel](Connection-and-Channel.md)** — the new per-accepted-client object.
