# UDP server

UDP is connectionless: a single listening socket fields datagrams from
every peer. `initphp/socket` makes the abstraction look more
connection-shaped without lying about it.

## What "connection" means for UDP here

Each unique `peerHost:peerPort` seen on the wire gets its own
`UdpChannel` and `ServerConnection`. The server demultiplexes inbound
datagrams into the right channel via the channel's internal buffer
(`feed()`), so your callback can call `$conn->read()` and get only the
datagrams that came from that peer.

`broadcast()` sends to every tracked peer. There is **no peer
discovery** — a peer exists only after it has spoken to the server.

## Quick reference

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::UDP, '0.0.0.0', 9000);
$server->listen();

$server->live(function ($srv, $conn) {
    $payload = $conn->read(65535);
    if ($payload !== null) {
        $conn->write("pong: {$payload}");
    }
});
```

## Datagram sizing

The internal read size is `UdpChannel::MAX_DATAGRAM = 65535`. The
practical upper bound for a UDP payload over IPv4 is **65 507 bytes**;
keep messages comfortably under MTU (≈1472 bytes on Ethernet) if you
care about avoiding fragmentation.

## No backlog, no listen() call

UDP doesn't have a listen queue, so `backlog()` does not exist. The
`listen()` method only performs `socket_create` + `socket_bind` for
this transport.

## Caveats

- **No reliability, no ordering.** That's UDP. If your protocol needs
  retransmits, build them on top.
- **Peer eviction is your job.** The server never removes a UDP
  connection on its own — you decide when a silent peer has gone away
  (heartbeat, TTL, etc.) and call `$conn->close()`.
- **No `isAlive()` truth.** `UdpChannel::isAlive()` simply tracks
  whether you have closed it; the protocol cannot tell you the peer is
  gone.
