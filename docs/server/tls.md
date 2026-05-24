# TLS server

TLS servers wrap `stream_socket_server` with an `ssl://` context (`tls`
scheme). Both the listen and the per-client handshake go through PHP
stream encryption.

## Quick reference

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::TLS, '127.0.0.1', 8443, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem')   // cert + private key
    ->option('verify_peer', false)
    ->option('allow_self_signed', true);

$server->listen();
$server->live(function ($srv, $conn) {
    $payload = $conn->read();
    if ($payload !== null) {
        $conn->write("secure: {$payload}");
    }
});
```

## Required SSL context options

At minimum the server needs `local_cert` (a PEM bundle of the
certificate and its private key). Every option you can pass to PHP's
SSL context is also available here — see the
[PHP manual](https://www.php.net/manual/en/context.ssl.php).

Common keys:

| Option | Why you might set it |
| --- | --- |
| `local_cert` | Path to a PEM file with the server certificate (and optionally the private key). |
| `local_pk` | Path to the private key if it lives in a separate file. |
| `passphrase` | Passphrase protecting the private key. |
| `cafile` / `capath` | Trusted CA bundle if you ask clients to authenticate. |
| `verify_peer` | Default `true` — drop the connection if the peer cert can't be verified. |
| `verify_peer_name` | Default `true` — match the certificate CN/SAN against the peer name. |
| `allow_self_signed` | Useful in dev; **do not** ship it. |

## Fluent helpers

| Method | Purpose |
| --- | --- |
| `option(string $key, mixed $value)` | Set any SSL context option. |
| `timeout(float $seconds)` | Default socket / handshake timeout. |
| `blocking(bool $mode = true)` | Whether accepted client streams stay blocking. Default `false`. |
| `crypto(?CryptoMethod $method)` | Pin a specific cipher family (`CryptoMethod::TLSv1_2`, …) or `null` to defer to the URL scheme. |

## Generating a self-signed certificate for local testing

```bash
openssl req -x509 -newkey rsa:2048 -nodes -keyout key.pem -out cert.pem \
    -days 365 -subj '/CN=localhost'
cat cert.pem key.pem > server.pem
```

Point `option('local_cert', ...)` at the resulting `server.pem`.

## Caveats

- **Handshake happens during accept.** `live()` / `tick()` may briefly
  block during the TLS exchange of a new connection. For loopback
  this is in the low milliseconds; for high-fan-in remote workloads,
  consider terminating TLS in a reverse proxy.
- **The listening stream stays in blocking mode** so the handshake has
  room to complete inside `stream_socket_accept`'s timeout. `select()`
  still drives readiness — the loop will not idle on a blocking
  accept call.
- **Verify the peer in production.** The defaults assume you mean it
  when you say "verify_peer=false". For real deployments, point
  `cafile` at the trust anchor and leave `verify_peer` / `verify_peer_name`
  on.
