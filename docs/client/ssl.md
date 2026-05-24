# SSL client

`InitPHP\Socket\Client\SSL` opens an `ssl://` stream. Functionally
identical to the [TLS client](./tls.md); the URL scheme just affects
PHP's default cipher negotiation.

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

Refer to the [TLS client guide](./tls.md) for the full option list and
error-handling notes — every helper translates directly.

## When to pick `SSL` vs `TLS`

Stick with `Transport::TLS` unless a specific server only accepts
`ssl://`. Modern peers negotiate TLS 1.2 / 1.3 either way; the
`ssl://` scheme is mostly useful for compatibility with legacy
endpoints.

To force a specific protocol version, pin it via `crypto()`:

```php
use InitPHP\Socket\Enum\CryptoMethod;

$client->crypto(CryptoMethod::TLSv1_2);
```
