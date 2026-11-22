# Recipe — Echo Server

The smallest server that demonstrates accept, read and write. We do it twice — once over TCP, once over UDP — so the differences are visible side by side.

## TCP echo

`tcp-echo-server.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Interfaces\{SocketServerInterface, SocketConnectionInterface};

$server = Socket::server(Transport::TCP, '127.0.0.1', 9000);
$server->listen();

echo "TCP echo on 127.0.0.1:9000\n";

$server->live(function (SocketServerInterface $srv, SocketConnectionInterface $conn) {
    $payload = $conn->read(1024);
    if ($payload === null) {
        return;
    }
    $conn->write("echo: {$payload}");
});
```

```bash
php tcp-echo-server.php
```

From another terminal:

```bash
$ nc 127.0.0.1 9000
hi
echo: hi
```

### A matching TCP client

`tcp-echo-client.php`:

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

## UDP echo

`udp-echo-server.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Interfaces\{SocketServerInterface, SocketConnectionInterface};

$server = Socket::server(Transport::UDP, '127.0.0.1', 9001);
$server->listen();

echo "UDP echo on 127.0.0.1:9001\n";

$server->live(function (SocketServerInterface $srv, SocketConnectionInterface $conn) {
    $payload = $conn->read(65535);
    if ($payload === null) {
        return;
    }
    $conn->write("echo: {$payload}");
});
```

```bash
php udp-echo-server.php
```

From another terminal:

```bash
$ echo -n 'ping' | nc -u -w1 127.0.0.1 9001
echo: ping
```

### A matching UDP client

`udp-echo-client.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::UDP, '127.0.0.1', 9001);
$client->connect();

$client->write('ping');
echo $client->read(65535);

$client->disconnect();
```

## What changes between TCP and UDP

- The transport enum case (`TCP` vs `UDP`).
- Read buffer size: 1 KiB is plenty for ad-hoc TCP framing; UDP needs to be big enough for one whole datagram (`UdpChannel::MAX_DATAGRAM = 65535`).
- UDP has no half-close and no notion of disconnection on the server side. If a client stops talking, the server still keeps its `UdpChannel`. Evict idle peers with an application-level TTL — see [Transport UDP](Transport-UDP.md).

## What stays the same

- Lifecycle: `listen()` → `live(callback)` → `close()` (clean shutdown).
- The callback signature `(SocketServerInterface, SocketConnectionInterface)`.
- `$conn->read()` returns `?string`, `$conn->write()` returns `?int`.

## See also

- **[Transport TCP](Transport-TCP.md)** / **[Transport UDP](Transport-UDP.md)** — transport-specific details.
- **[Recipe Broadcast](Recipe-Broadcast.md)** — fan out a message to multiple peers.
- **[Event Loop Integration](Event-Loop-Integration.md)** — drive the same server from `tick()` instead of `live()`.
