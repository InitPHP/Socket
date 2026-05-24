# SSL server

`InitPHP\Socket\Server\SSL` is the `ssl://` scheme counterpart of
[TLS](./tls.md). The class is functionally identical — the only
difference is the URL scheme passed to `stream_socket_server`, which
affects the default ciphers PHP selects.

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::SSL, '127.0.0.1', 8443, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem');

$server->listen();
```

Refer to the [TLS server guide](./tls.md) for the full option list,
handshake notes and certificate setup; everything translates directly.

## When to pick `SSL` vs `TLS`

Prefer `Transport::TLS` unless you specifically need the legacy SSLv23
/ SSLv2 / SSLv3 fallback selection. Modern peers negotiate TLS 1.2 /
1.3 either way; the `ssl://` scheme exists for compatibility with old
PHP code and rarely gives you anything new.

If you need a specific protocol version, set it explicitly with
`crypto()`:

```php
use InitPHP\Socket\Enum\CryptoMethod;

$server->crypto(CryptoMethod::TLSv1_2);
```
