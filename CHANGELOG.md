# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning 2.0.0](https://semver.org/).

## [Unreleased]

## [2.0.0] — TBD

### Highlights

The 2.0 line is a clean break from 1.x — the previous server loop had
race conditions, lost inbound data through its liveness check, and
stored transport state on a `static` property that leaked between
instances. The new shape ships with explicit enums, per-transport
`Channel` strategies, a non-blocking `select`-driven loop and full
PHP 8.1+ typing.

### Added

- **PHP 8.1+ enums** — `Transport`, `Domain` and `CryptoMethod` replace
  magic integer / string flags.
- **`ChannelInterface` + `TcpChannel` / `UdpChannel` / `StreamChannel`** —
  per-transport I/O strategy. `ServerConnection` is now just identity
  plus delegation.
- **`SocketExceptionInterface`** — marker implemented by every exception
  in the package, so a single catch covers them all.
- **`tick(callable, float): int`** — single-iteration accept/dispatch
  method on every server. Use it to embed the package in your own
  event loop or to drive servers deterministically in tests.
- **`stop()` / `isRunning()`** — cooperative shutdown for the `live()`
  loop.
- **`register(int|string, SocketConnectionInterface): bool`** — promoted
  to `SocketServerInterface`; the 1.x package-private `clientRegister()`
  is replaced by an interface method with a stable contract.
- **PHPUnit 10 test suite** — 36 unit + integration tests covering
  enums, exception hierarchy, factory, channels, broadcast/register
  logic, TCP echo, UDP per-peer routing, TLS handshake (forked).
- **CI pipeline** — GitHub Actions matrix across PHP 8.1, 8.2, 8.3 with
  PHPStan level 8, PHP-CS-Fixer and Codecov upload.
- **`docs/` directory** — getting started, architecture, per-transport
  server and client guides, cookbook (chat server, raw SMTP) and the
  migration guide.

### Changed

- **Minimum PHP version** is now `^8.1` (was `>=7.4`).
- **`composer.json`** now requires `ext-openssl` in addition to
  `ext-sockets`.
- **Server method renames** — `connection()` → `listen()`,
  `disconnect()` → `close()`. `live()` signature now takes
  `float $idleSeconds` instead of `int $usleep`. `wait()` is typed
  `float $seconds`.
- **`SocketServerClientInterface` renamed to `SocketConnectionInterface`.**
  `push()` is now `write()`. `isDisconnected()` is replaced by the
  non-destructive `isAlive()`.
- **Exception hierarchy** — `SocketException` extends
  `\RuntimeException`. `SocketConnectionException` and
  `SocketListenException` now extend `SocketException` (previously
  `\Exception`). `SocketInvalidArgumentException` still extends
  `\InvalidArgumentException` and additionally implements
  `SocketExceptionInterface`.

### Removed

- `Common/BaseClient`, `Common/BaseCommon`, `Common/BaseServer`,
  `Common/ServerTrait`, `Common/StreamClientTrait`,
  `Common/StreamServerTrait` — replaced by `Client/AbstractClient`,
  `Client/AbstractStreamClient`, `Server/AbstractServer` and
  `Server/AbstractStreamServer`.
- `Server/ServerClient` — replaced by `Server/ServerConnection`.
- `Interfaces/SocketServerClientInterface` — replaced by
  `Interfaces/SocketConnectionInterface`.
- `Socket::TCP`, `Socket::UDP`, `Socket::TLS`, `Socket::SSL` integer
  constants — replaced by the `Transport` enum.
- The mystery-typed `$argument` parameter on the factory and on every
  constructor — replaced by explicit `?Domain $domain` / `?float $timeout`
  named parameters.
- All `__setSocket()` / `__setCallbacks()` / `__removeCallbacks()`
  magic-prefixed methods.
- The static `ServerClient::$credentials` array that leaked transport
  state between server instances.
- `echo` calls inside `ServerClient` that wrote
  `"New client connected."` / `"Client disconnected."` to STDOUT.

### Fixed

- **Server loop accepts more than one client.** The 1.x `connection()`
  performed a blocking `socket_accept()` before `live()` was even
  called, capping the server at a single connection per process.
- **TLS / SSL servers use the right accept call.** The 1.x loop called
  `socket_accept()` on a stream resource (always wrong for TLS / SSL)
  and never accepted the second connection.
- **Liveness checks no longer consume data.** The 1.x
  `isDisconnected()` read a line off every client every iteration
  and discarded it, so application-level reads never saw any data.
- **Client id map survives disconnects.** The 1.x `clientMap` stored
  raw array indices that became dangling after `unset()` on a
  disconnected client. The new `clientIdMap` uses monotonic keys and
  cleans up on eviction.
- **UDP server demultiplexes peers correctly.** The 1.x server
  registered the listening socket itself as a "client" and broadcast
  back to its own host/port.
- **TLS handshake has enough time.** The 1.x server left the listening
  stream non-blocking and called `stream_socket_accept(..., 0.0)`,
  which prevented the handshake from completing under load. The new
  loop keeps the listening stream blocking and drives readiness via
  `stream_select`, giving the handshake the full timeout.

### Migration

See [`docs/migration-1.x-to-2.x.md`](./docs/migration-1.x-to-2.x.md) for
a step-by-step upgrade guide.

[Unreleased]: https://github.com/InitPHP/Socket/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/InitPHP/Socket/releases/tag/v2.0.0
