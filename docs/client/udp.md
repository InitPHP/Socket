# UDP client

A datagram client. After `connect()`, the kernel locks the peer
address; `read()` / `write()` then behave like a stream socket and
talk only to that peer.

## Quick reference

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::UDP, '127.0.0.1', 9000);
$client->connect();

$client->write('ping');
echo $client->read(65535);

$client->disconnect();
```

## Flags

Both `read()` and `write()` accept an optional `int $flags` argument:

| Flag set | Sensible places |
| --- | --- |
| `MSG_OOB`, `MSG_PEEK`, `MSG_WAITALL`, `MSG_DONTWAIT` | `read()` |
| `MSG_OOB`, `MSG_EOR`, `MSG_EOF`, `MSG_DONTROUTE` | `write()` |

Leave them at `0` unless you are sure you need them.

## Caveats

- **No retransmits.** A successful `write()` only proves the OS
  accepted the packet for sending. Datagram loss is silent.
- **No connection state.** `disconnect()` only closes the local
  socket; the peer has no way to learn the client is gone.
- **Packet size.** UDP over IPv4 maxes out at 65 507 bytes of payload.
  Stay under MTU (≈1472 bytes on Ethernet) to avoid fragmentation.
