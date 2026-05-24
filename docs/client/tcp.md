# TCP client

A thin wrapper over `socket_create` + `socket_connect` for stream
sockets.

## Quick reference

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\{Transport, Domain};

$client = Socket::client(Transport::TCP, '127.0.0.1', 8080, Domain::V4);
$client->connect();

$client->write("PING\n");
echo $client->read(1024);

$client->disconnect();
```

## Read modes

`read(int $length = 1024, int $type = PHP_BINARY_READ)` accepts either
of the two PHP reading modes:

| Constant | Behaviour |
| --- | --- |
| `PHP_BINARY_READ` (default) | Read up to `$length` raw bytes. |
| `PHP_NORMAL_READ` | Read until `\n` or `\r` is seen (line-oriented). |

## Error handling

`connect()` throws `SocketConnectionException` if the remote endpoint
refuses or the OS can't open the socket. `read()` / `write()` return
`null` instead of raising — wrap with your own retry logic if you
need it.

```php
try {
    $client->connect();
} catch (\InitPHP\Socket\Exception\SocketConnectionException $e) {
    // retry, fall back, log, ...
}
```

## UNIX domain sockets

Pass `Domain::UNIX` and the filesystem path as the `host`:

```php
$client = Socket::client(Transport::TCP, '/tmp/initphp.sock', 1, Domain::UNIX);
$client->connect();
```

(The `port` argument is ignored for UDS, but must satisfy the `>0`
check — pass any placeholder.)
