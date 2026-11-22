# Recipe — SMTP Client (raw)

A minimal raw SMTP client that opens an implicit-TLS connection to Gmail's port 465 and performs the initial `EHLO` exchange. Useful as a smoke test for the TLS client and to learn the protocol; **do not** ship this as a real mailer — use `initphp/mailer` or another battle-tested library for that.

## The script

`smtp-probe.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::SSL, 'smtp.gmail.com', 465, timeout: 10.0)
    ->option('verify_peer',      false)
    ->option('verify_peer_name', false);

$client->connect();

echo $client->read(1024);             // 220 banner
$client->write("EHLO localhost\r\n");
echo $client->read(1024);             // 250 capabilities
$client->write("QUIT\r\n");
echo $client->read(1024);             // 221 closing

$client->disconnect();
```

```bash
php smtp-probe.php
```

Expected output (truncated):

```
220 smtp.gmail.com ESMTP …
250-smtp.gmail.com at your service, …
250-SIZE …
250-STARTTLS
250 SMTPUTF8
221 2.0.0 closing connection
```

## Why these knobs

- **`Transport::SSL`** — Gmail's port 465 serves implicit TLS; the connection is encrypted from the first byte. For port 587 you would start with `Transport::TCP` and upgrade in place with `crypto()` after `STARTTLS`.
- **`timeout: 10.0`** — the connect (and TLS handshake) must finish inside this budget.
- **`verify_peer`** is set to `false` here to keep the snippet self-contained. **In production** point `cafile` at the system trust store (`/etc/ssl/certs/ca-certificates.crt` on Debian/Ubuntu, `/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem` on RHEL/Alma) and leave `verify_peer` on.

## Reading SMTP responses cleanly

SMTP replies are line-oriented and may span multiple lines for one logical response (`250-foo` continuation, `250 bar` final). Looping until the final-line marker shows up makes the read robust:

```php
function smtpRead(\InitPHP\Socket\Interfaces\SocketClientInterface $client): string
{
    $out = '';
    while (($chunk = $client->read(1024)) !== null) {
        $out .= $chunk;
        // RFC 5321: final line of a multi-line response uses "<code><space>".
        if (preg_match('/^\d{3} [^\r\n]*\r?\n$/m', $chunk) === 1) {
            break;
        }
    }
    return $out;
}
```

Use it in place of the raw `$client->read(1024)` calls in the script above.

## A real STARTTLS flow (port 587)

For SMTP submission over `STARTTLS`, the protocol starts unencrypted and upgrades:

```php
use InitPHP\Socket\Enum\{Transport, CryptoMethod};

$client = Socket::client(Transport::TCP, 'smtp.example.com', 587, /* no TLS yet */);
$client->connect();
// 220 banner...
echo $client->read(1024);

$client->write("EHLO localhost\r\n");
echo $client->read(1024);

$client->write("STARTTLS\r\n");
echo $client->read(1024);
// 220 Ready to start TLS
```

> The package's `Client\TCP` does not expose a TLS upgrade — `crypto()` lives on the stream-backed clients (`TLS` / `SSL`). For a true STARTTLS flow you would call PHP's `stream_socket_enable_crypto($client->getSocket(), true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)` directly on the underlying socket. Or open with `Transport::TLS` and use `crypto()` once connected if your peer accepts the deferred handshake.

## Building this into a sender

For production mail handling, use a mail library that knows about AUTH, MIME, headers and retries. The example above is a probe — it just shows that the TLS client connects and reads SMTP greetings. See `initphp/mailer` for the full client.

## See also

- **[Transport TLS](Transport-TLS.md)** / **[Transport SSL](Transport-SSL.md)** — reference for the stream-backed clients.
- **[SSL Context Options](SSL-Context-Options.md)** — every key `option()` accepts.
- **[Recipe Self Signed TLS](Recipe-Self-Signed-TLS.md)** — local-loopback equivalent for testing.
