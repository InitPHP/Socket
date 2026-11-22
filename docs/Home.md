# InitPHP Socket — Documentation

Welcome to the official documentation for **`initphp/socket`** — a lightweight TCP, UDP, TLS and SSL socket server / client toolkit for PHP 8.2+.

```bash
composer require initphp/socket
```

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::TCP, '127.0.0.1', 8080);
$server->listen();
$server->live(function ($srv, $conn) {
    $payload = $conn->read(1024);
    if ($payload !== null) {
        $conn->write("echo: {$payload}");
    }
});
```

The package gives you four building blocks every long-running PHP service ends up writing by hand:

| Layer | Class / Concept | Purpose |
|---|---|---|
| **Factory** | `Socket::server()` / `Socket::client()` | One entry point dispatching by `Transport` enum |
| **Servers** | `Server\{TCP, UDP, TLS, SSL}` | Bind, listen and dispatch with a `select`-driven loop |
| **Clients** | `Client\{TCP, UDP, TLS, SSL}` | Connect-read-write to a remote endpoint |
| **Channels** | `Channel\{Tcp, Udp, Stream}Channel` | Per-transport I/O strategy used by `ServerConnection` |

## Start here

- **[Installation](Installation.md)** — composer, requirements, optional extensions.
- **[Quick Start](Quick-Start.md)** — bind, accept, echo in under 20 lines.
- **[Architecture](Architecture.md)** — how the factory, channels, loop and connections fit together.

## Transports

- **[Transport TCP](Transport-TCP.md)** — stream-oriented sockets, `ext-sockets` backend.
- **[Transport UDP](Transport-UDP.md)** — datagram sockets with per-peer routing.
- **[Transport TLS](Transport-TLS.md)** — `tls://` streams, modern handshake.
- **[Transport SSL](Transport-SSL.md)** — `ssl://` streams, legacy fallback.

## API

- **[Server Lifecycle](Server-Lifecycle.md)** · **[Client Lifecycle](Client-Lifecycle.md)** — what each method does and when.
- **[Connection and Channel](Connection-and-Channel.md)** — the per-accepted-client object and its strategy.
- **[Event Loop Integration](Event-Loop-Integration.md)** — embedding the server in your own loop with `tick()`.
- **[API Reference](API-Reference.md)** — single-page method matrix.
- **[Enums](Enums.md)** · **[Exceptions](Exceptions.md)** · **[SSL Context Options](SSL-Context-Options.md)**

## Recipes

- **[Recipe Echo Server](Recipe-Echo-Server.md)** — minimal TCP / UDP echo.
- **[Recipe Chat Server](Recipe-Chat-Server.md)** — registered clients + targeted broadcast.
- **[Recipe SMTP Client](Recipe-SMTP-Client.md)** — raw `EHLO` over implicit TLS.
- **[Recipe Broadcast](Recipe-Broadcast.md)** — fan-out to all / by-id / by-list.
- **[Recipe Self Signed TLS](Recipe-Self-Signed-TLS.md)** — generate a cert, bind a TLS server, connect from a client.
- **[Recipe Custom Channel](Recipe-Custom-Channel.md)** — write your own transport strategy.

## Operational

- **[Testing Strategy](Testing-Strategy.md)** — unit + integration patterns we use ourselves.
- **[Migration Guide](Migration-Guide.md)** — moving from 1.x to 2.x.
- **[FAQ](FAQ.md)** — common questions and gotchas.

---

> 2.x line · PHP 8.2+ · MIT · [GitHub](https://github.com/InitPHP/Socket) · [Packagist](https://packagist.org/packages/initphp/socket)
