# Quick Start

A complete TCP echo server, a matching client, and a five-minute tour of the API.

## A working server

Create `server.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Interfaces\{SocketServerInterface, SocketConnectionInterface};

$server = Socket::server(Transport::TCP, '127.0.0.1', 9000);
$server->listen();

echo "Listening on 127.0.0.1:9000\n";

$server->live(function (SocketServerInterface $srv, SocketConnectionInterface $conn) {
    $payload = $conn->read(1024);
    if ($payload === null) {
        return;
    }
    $conn->write("echo: {$payload}");
});
```

Run it:

```bash
php server.php
```

## A working client

Create `client.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::TCP, '127.0.0.1', 9000);
$client->connect();

$client->write("hello\n");
echo $client->read(1024);   // -> "echo: hello\n"

$client->disconnect();
```

Run it from a second terminal:

```bash
php client.php
```

You will see `echo: hello` printed back. The server keeps running until you stop it (Ctrl-C). You can also connect with `nc 127.0.0.1 9000` to talk to it interactively.

## What just happened

The factory built a concrete `Server\TCP` instance, `listen()` bound and started listening, and `live()` ran a non-blocking accept/dispatch loop that calls the callback whenever a connection has new inbound data.

```
Socket::server(Transport::TCP, ...)
        │
        ▼
   Server\TCP            ┌──────────────────────────────┐
        │   listen()  →  │ socket_create + bind + listen│
        │   live()    →  │ while (running) { tick(); }  │
        │   tick()    →  │ socket_select + accept       │
        ▼                │ dispatch readable clients     │
   ServerConnection      └──────────────────────────────┘
        │
        ▼
   TcpChannel  (read / write / isAlive / close)
```

## Switching transports

The same factory shape works for every transport — only the enum case changes:

```php
Socket::server(Transport::TCP, '127.0.0.1', 9000);
Socket::server(Transport::UDP, '127.0.0.1', 9001);
Socket::server(Transport::TLS, '127.0.0.1', 9443, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem');
Socket::server(Transport::SSL, '127.0.0.1', 9444, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem');
```

Clients mirror servers:

```php
Socket::client(Transport::TCP, '127.0.0.1', 9000);
Socket::client(Transport::UDP, '127.0.0.1', 9001);
Socket::client(Transport::TLS, 'example.com', 443, timeout: 5.0)
    ->option('cafile', '/etc/ssl/certs/ca-certificates.crt');
```

## Tick instead of live

`live()` is just `while ($this->isRunning()) $this->tick(...)`. If your application already has an event loop, drive the server from there:

```php
$server->listen();

while ($app->running) {
    $events = $server->tick(fn ($srv, $conn) => /* … */, waitSeconds: 0.0);
    if ($events === 0) {
        $app->yield();
    }
}
```

See [Event Loop Integration](Event-Loop-Integration.md) for the details.

## Where to next

- **[Architecture](Architecture.md)** — the model.
- **[Server Lifecycle](Server-Lifecycle.md)** — what `listen` / `live` / `tick` / `close` actually do.
- **[Recipe Chat Server](Recipe-Chat-Server.md)** — a richer end-to-end example with `register()` and `broadcast()`.
