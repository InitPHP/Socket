# initphp/socket

[![Latest Stable Version](https://poser.pugx.org/initphp/socket/v)](https://packagist.org/packages/initphp/socket)
[![Total Downloads](https://poser.pugx.org/initphp/socket/downloads)](https://packagist.org/packages/initphp/socket)
[![License](https://poser.pugx.org/initphp/socket/license)](https://packagist.org/packages/initphp/socket)
[![PHP Version Require](https://poser.pugx.org/initphp/socket/require/php)](https://packagist.org/packages/initphp/socket)
[![CI](https://github.com/InitPHP/Socket/actions/workflows/ci.yml/badge.svg)](https://github.com/InitPHP/Socket/actions/workflows/ci.yml)

A lightweight TCP, UDP, TLS and SSL socket toolkit for PHP 8.1+. Both server
and client sides share a clean, typed API built around enums and a small
`Channel` strategy so each transport plugs in without `switch` ladders.

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::TCP, '127.0.0.1', 8080);
$server->listen();
$server->live(function ($srv, $conn) {
    $message = $conn->read(1024);
    $conn->write("echo: {$message}");
});
```

## Requirements

- PHP **8.1+**
- ext-sockets
- ext-openssl (TLS / SSL)
- ext-pcntl (only for the integration test suite)

## Installation

```bash
composer require initphp/socket
```

## Features

- **TCP, UDP, TLS, SSL** — one factory, one interface per side.
- **Non-blocking, select-driven server loop** — `live()` runs forever, or
  drive the loop yourself one iteration at a time with `tick()` (great for
  embedding into your own event loop or for deterministic tests).
- **First-class enums** — `Transport`, `Domain` and `CryptoMethod` replace
  magic strings and integer flags.
- **Strategy-based channels** — `TcpChannel`, `UdpChannel` and
  `StreamChannel` isolate the transport-specific I/O. No static state shared
  between server instances.
- **Coherent exception hierarchy** — every exception implements
  `SocketExceptionInterface`, so a single catch covers the package.
- **Typed everywhere** — PHP 8.1 enums, readonly promoted properties, full
  `declare(strict_types=1)` coverage.
- **No silent data loss** — liveness checks never consume data from the
  wire.

## Quick start

### TCP echo server

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Interfaces\{SocketServerInterface, SocketConnectionInterface};

$server = Socket::server(Transport::TCP, '127.0.0.1', 8080);
$server->listen();

$server->live(function (SocketServerInterface $srv, SocketConnectionInterface $conn) {
    $message = $conn->read(1024);
    if ($message === null) {
        return;
    }
    if ($message === 'quit') {
        $conn->write("bye\n");
        $conn->close();
        return;
    }
    $conn->write("echo: {$message}");
});
```

### TCP client

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::TCP, '127.0.0.1', 8080);
$client->connect();

$client->write("hello\n");
echo $client->read(1024);

$client->disconnect();
```

### TLS server (chat-style with named clients)

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Interfaces\{SocketServerInterface, SocketConnectionInterface};

$server = Socket::server(Transport::TLS, '127.0.0.1', 8443, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem')
    ->option('allow_self_signed', true);

$server->listen();

$server->live(function (SocketServerInterface $srv, SocketConnectionInterface $conn) {
    $input = $conn->read();
    if ($input === null) {
        return;
    }
    if (\preg_match('/^REGISTER\s+([\w-]{3,})$/i', $input, $m) === 1) {
        $srv->register($m[1], $conn);
        $conn->write("Welcome, {$m[1]}\n");
        return;
    }
    if (\preg_match('/^SEND\s+@([\w-]+)\s+(.*)$/i', $input, $m) === 1) {
        $srv->broadcast($m[2], $m[1]);
        return;
    }
    $srv->broadcast(\trim($input));
});
```

### SSL client (talking to Gmail SMTP)

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::SSL, 'smtp.gmail.com', 465, timeout: 10.0)
    ->option('verify_peer', false)
    ->option('verify_peer_name', false);

$client->connect();
$client->write("EHLO [127.0.0.1]\r\n");
echo $client->read(1024);
$client->disconnect();
```

## API surface

### Factory — `InitPHP\Socket\Socket`

```php
Socket::server(
    Transport $transport,
    string $host,
    int $port,
    ?Domain $domain = null,   // Defaults to Domain::V4 for TCP/UDP. Ignored for TLS/SSL.
    ?float $timeout = null,   // Connect/handshake timeout for TLS/SSL.
): SocketServerInterface;

Socket::client(
    Transport $transport,
    string $host,
    int $port,
    ?Domain $domain = null,
    ?float $timeout = null,
): SocketClientInterface;
```

### Servers — `InitPHP\Socket\Interfaces\SocketServerInterface`

| Method | Purpose |
| --- | --- |
| `listen(): static` | Bind and start listening. Does **not** accept clients. |
| `live(callable $cb, float $idle = 0.05): void` | Run the accept/dispatch loop until `stop()` is called. |
| `tick(callable $cb, float $wait = 0.0): int` | One iteration of the loop. Returns events processed. |
| `stop(): void` | Cooperatively exit the loop started by `live()`. |
| `close(): bool` | Tear everything down (every client + the listening socket). |
| `broadcast(string $msg, int\|string\|array\|null $ids = null): bool` | Send to all clients or a targeted subset. |
| `register(int\|string $id, SocketConnectionInterface $conn): bool` | Attach an addressable id to a connection. |
| `getClients(): array` | Map of `id|key → connection`. |

`AbstractStreamServer` (TLS / SSL) additionally exposes:

```php
$server->option(string $key, mixed $value): static  // SSL stream context option
$server->timeout(float $seconds): static
$server->blocking(bool $mode = true): static
$server->crypto(?CryptoMethod $method): static
```

### Clients — `InitPHP\Socket\Interfaces\SocketClientInterface`

| Method | Purpose |
| --- | --- |
| `connect(): static` | Open the connection. |
| `disconnect(): bool` | Close the connection. Idempotent. |
| `read(int $len = 1024): ?string` | Receive up to `$len` bytes. Returns `null` on no data / failure. |
| `write(string $data): ?int` | Send `$data`. Returns the number of bytes written, or `null` on failure. |

`AbstractStreamClient` (TLS / SSL) adds `option()`, `timeout()`, `blocking()`
and `crypto()` — same shape as the server side.

### Enums

```php
InitPHP\Socket\Enum\Transport     // TCP, UDP, TLS, SSL
InitPHP\Socket\Enum\Domain        // V4, V6, UNIX
InitPHP\Socket\Enum\CryptoMethod  // SSLv2/3/23, ANY, TLS, TLSv1_0/1_1/1_2
```

### Exceptions

Every package exception implements `SocketExceptionInterface`, so a single
`catch (SocketExceptionInterface $e)` covers them all.

```
SocketExceptionInterface
├── SocketException                 (extends \RuntimeException)
│   ├── SocketConnectionException
│   └── SocketListenException
└── SocketInvalidArgumentException  (extends \InvalidArgumentException)
```

## Embedding into your own event loop

If you already run an event loop (ReactPHP, Amp, Swoole-bridge, etc.), do
not call `live()` — invoke `tick()` from your loop and let the host decide
when to yield:

```php
$server->listen();

while ($yourEventLoop->running()) {
    $events = $server->tick(function ($srv, $conn) { /* ... */ }, waitSeconds: 0.0);
    if ($events === 0) {
        $yourEventLoop->yield();
    }
}
```

## Documentation

In-depth guides live under [`docs/`](./docs):

- [Getting started](./docs/getting-started.md)
- [Architecture](./docs/architecture.md)
- Servers: [TCP](./docs/server/tcp.md) · [UDP](./docs/server/udp.md) · [TLS](./docs/server/tls.md) · [SSL](./docs/server/ssl.md)
- Clients: [TCP](./docs/client/tcp.md) · [UDP](./docs/client/udp.md) · [TLS](./docs/client/tls.md) · [SSL](./docs/client/ssl.md)
- Cookbook: [Chat server](./docs/cookbook/chat-server.md) · [SMTP client](./docs/cookbook/smtp-client.md)
- [Migrating from 1.x](./docs/migration-1.x-to-2.x.md)

## Development

```bash
composer install
composer test       # PHPUnit (unit + integration)
composer stan       # PHPStan level 8
composer cs-check   # PHP-CS-Fixer dry-run
composer cs-fix     # Apply style fixes
composer qa         # All of the above
```

CI runs the full QA pipeline on PHP 8.1, 8.2 and 8.3.

## Contributing

Issues, ideas and pull requests are welcome. Please read the
[org-wide contributing guide](https://github.com/InitPHP/.github/blob/main/CONTRIBUTING.md)
before opening a PR.

Security issues should be reported privately — see
[SECURITY.md](https://github.com/InitPHP/.github/blob/main/SECURITY.md).

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) — `<info@muhammetsafak.com.tr>`

## License

Released under the [MIT License](./LICENSE).
