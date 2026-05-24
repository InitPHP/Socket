# Getting started

`initphp/socket` is a thin layer on top of the PHP `sockets` extension and
the stream socket family. It gives you a transport-agnostic factory, a
non-blocking server loop and a small `Channel` abstraction so the same
mental model fits TCP, UDP, TLS and SSL.

## Install

```bash
composer require initphp/socket
```

You will also need `ext-sockets` (always) and `ext-openssl` (for TLS / SSL).

## The five-minute tour

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Interfaces\{SocketServerInterface, SocketConnectionInterface};

// 1. Build a server. Nothing has happened on the network yet.
$server = Socket::server(Transport::TCP, '127.0.0.1', 9000);

// 2. Bind and listen.
$server->listen();

// 3. Run the accept/dispatch loop. The callback is invoked whenever a
//    connection has new inbound data.
$server->live(function (SocketServerInterface $srv, SocketConnectionInterface $conn) {
    $payload = $conn->read(1024);
    if ($payload === null) {
        return;
    }
    $conn->write("got {$payload}");
});
```

The same pattern works for every transport — only the `Transport` case
changes:

```php
Socket::server(Transport::TCP, '127.0.0.1', 9000);
Socket::server(Transport::UDP, '127.0.0.1', 9001);
Socket::server(Transport::TLS, '127.0.0.1', 9443, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem');
Socket::server(Transport::SSL, '127.0.0.1', 9444, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem');
```

## Clients mirror servers

```php
$client = Socket::client(Transport::TCP, '127.0.0.1', 9000);
$client->connect();
$client->write("hello\n");
echo $client->read(1024);
$client->disconnect();
```

## Lifecycle

A server moves through three states:

```
constructed → listening → running → closed
              listen()    live()    close()
```

A client is a two-step affair:

```
constructed → connected → closed
              connect()   disconnect()
```

Both servers and clients can be re-built; you cannot reuse a closed
instance.

## Where to go next

- [Architecture overview](./architecture.md) — how the pieces fit together.
- [Server guides](./server/tcp.md) — transport-by-transport details.
- [Client guides](./client/tcp.md) — same on the connect side.
- [Cookbook](./cookbook/chat-server.md) — runnable examples.
- [Migrating from 1.x](./migration-1.x-to-2.x.md) — if you're coming
  from the previous major.
