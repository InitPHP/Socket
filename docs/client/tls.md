# TLS client

A `tls://` stream client. Use this for anything talking modern TLS:
HTTPS, secure SMTP, AMQP, etc.

## Quick reference

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::TLS, 'example.com', 443, timeout: 5.0);
$client->connect();

$client->write("GET / HTTP/1.0\r\nHost: example.com\r\n\r\n");
while (($chunk = $client->read(4096)) !== null) {
    echo $chunk;
}
$client->disconnect();
```

## SSL context options

`option(string $key, mixed $value)` is a passthrough for PHP's
[SSL context options](https://www.php.net/manual/en/context.ssl.php):

```php
$client = Socket::client(Transport::TLS, 'example.com', 443)
    ->option('verify_peer', true)
    ->option('verify_peer_name', true)
    ->option('cafile', '/etc/ssl/certs/ca-certificates.crt')
    ->option('SNI_enabled', true);
```

For development against a self-signed server:

```php
$client = Socket::client(Transport::TLS, '127.0.0.1', 8443)
    ->option('verify_peer', false)
    ->option('verify_peer_name', false)
    ->option('allow_self_signed', true);
```

> **Never ship `verify_peer => false`.** Always configure trust correctly
> in production. The development flags exist for testing only.

## Fluent helpers

| Method | Purpose |
| --- | --- |
| `option(string $key, mixed $value)` | Set any SSL context option. |
| `timeout(float $seconds)` | Connect / handshake timeout. Applied to live streams too. |
| `blocking(bool $mode = true)` | Default `true` — set to `false` for non-blocking I/O. |
| `crypto(?CryptoMethod $method)` | Toggle / pin a specific cipher family after `connect()`. |

## Error handling

`connect()` throws `SocketConnectionException` when:

- the TCP connect fails,
- the TLS handshake fails,
- or any underlying stream error is reported.

Inspect the message for the `(errno): description` PHP returned. For
"peer's CN does not match" or "self signed certificate" errors,
re-check the `verify_*` / `cafile` options above.
