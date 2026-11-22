# Transport — SSL

The `ssl://` scheme variant of [Transport TLS](Transport-TLS.md). Functionally identical class layout; the only difference is the URL scheme passed to `stream_socket_server` / `stream_socket_client`, which affects PHP's default cipher negotiation.

## When to choose `SSL` over `TLS`

In nearly every situation — **don't**. Pick `Transport::TLS`.

The `ssl://` scheme exists mostly for compatibility with legacy PHP code. Modern peers negotiate TLS 1.2 / 1.3 either way; the scheme just changes which cipher family PHP starts the negotiation in.

The one common case where `Transport::SSL` is the natural fit is **implicit TLS** services with a long-established convention of being called over `ssl://` — e.g. SMTPS on port 465, IMAPS on 993, POP3S on 995. Either transport works there; `SSL` is what existing PHP examples in the wild use.

## Server

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::SSL, '0.0.0.0', 8443, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem');

$server->listen();
$server->live(function ($srv, $conn) {
    $payload = $conn->read();
    if ($payload !== null) {
        $conn->write("ssl-echo: {$payload}");
    }
});
```

Every configuration knob, lifecycle method and exception type is identical to [Transport TLS](Transport-TLS.md) — read that page for the full reference.

## Client

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

## Pinning a specific protocol version

If you have a server that only speaks a specific version, pin it via `crypto()`:

```php
use InitPHP\Socket\Enum\CryptoMethod;

$client = Socket::client(Transport::SSL, 'legacy.example.com', 465);
$client->connect();
$client->crypto(CryptoMethod::TLSv1_2);   // upgrade or pin
```

For server-side, set the context option before `listen()`:

```php
$server->crypto(CryptoMethod::TLSv1_2);
```

See [Enums](Enums.md) for every `CryptoMethod` case and how it maps to PHP's `STREAM_CRYPTO_METHOD_*` constants.

## Why is the class still here?

The interface symmetry is worth more than the deduplication. Code that selects a transport by configuration value (`tls` vs `ssl`) maps trivially onto the `Transport` enum. Removing one or the other would force every caller to special-case.

## See also

- **[Transport TLS](Transport-TLS.md)** — the canonical reference. Everything there applies to SSL.
- **[SSL Context Options](SSL-Context-Options.md)** — every key `option()` accepts.
- **[Recipe SMTP Client](Recipe-SMTP-Client.md)** — a runnable example using `Transport::SSL` against port 465.
