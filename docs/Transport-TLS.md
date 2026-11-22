# Transport — TLS

`tls://` stream wrapper with modern cipher selection. Use this for anything that talks current TLS (HTTPS, SMTPS, AMQPS, custom encrypted protocols).

## Server

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::TLS, '0.0.0.0', 8443, timeout: 5.0)
    ->option('local_cert', __DIR__ . '/server.pem')
    ->option('verify_peer', false);

$server->listen();

$server->live(function ($srv, $conn) {
    $payload = $conn->read();
    if ($payload !== null) {
        $conn->write("secure: {$payload}");
    }
});
```

The TLS handshake happens **inside `stream_socket_accept`**. The package leaves the listening stream in blocking mode and uses the `$timeout` constructor argument as the handshake budget for each new client. `select()` still drives loop readiness — only the accept call itself is blocking.

### Required options

At minimum the server needs a certificate plus its private key. The simplest setup is a single PEM file with both:

```php
$server->option('local_cert', '/etc/myapp/server.pem');
```

If the key is encrypted, set `passphrase`. If the key is in a separate file, use `local_pk` for the private-key path. See [SSL Context Options](SSL-Context-Options.md) for the full list.

### Fluent setters

| Method | Default | Effect |
|---|---|---|
| `option(string $key, mixed $value)` | — | Set any SSL context option. |
| `timeout(float $seconds)` | `null` (uses `default_socket_timeout`) | Handshake budget for `stream_socket_accept`. |
| `blocking(bool $mode = true)` | `false` | Blocking mode of every newly accepted client stream. |
| `crypto(?CryptoMethod $method)` | `null` (URL scheme decides) | Pin a specific cipher family (`CryptoMethod::TLSv1_2`, …). `null` clears the override. |

`crypto()` writes into the SSL context as `crypto_method` and applies to subsequent accepts.

## Client

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::TLS, 'example.com', 443, timeout: 5.0)
    ->option('verify_peer', true)
    ->option('verify_peer_name', true)
    ->option('cafile', '/etc/ssl/certs/ca-certificates.crt')
    ->option('SNI_enabled', true);

$client->connect();

$client->write("GET / HTTP/1.0\r\nHost: example.com\r\n\r\n");
while (($chunk = $client->read(4096)) !== null) {
    echo $chunk;
}
$client->disconnect();
```

`connect()` runs `stream_socket_client` with the assembled context and verifies the certificate by default. The handshake completes inside `connect()`; once it returns, the stream is ready for application bytes.

### Configuring after connect

`timeout()` and `blocking()` are safe to call after `connect()` — they apply to the live stream:

```php
$client->connect();
$client->blocking(false);            // make subsequent reads non-blocking
$client->timeout(2.0);               // stream_set_timeout
```

`crypto()` toggles encryption on the live stream — useful for `STARTTLS`-style upgrades:

```php
use InitPHP\Socket\Enum\CryptoMethod;

$client = Socket::client(Transport::TLS, 'mail.example.com', 587);
$client->connect();                                // unencrypted at this point if you use tcp:// — see note below
// EHLO, STARTTLS exchange ...
$client->crypto(CryptoMethod::TLSv1_2);           // upgrade in place
```

> **Note:** `Transport::TLS` opens a `tls://` URL which negotiates TLS at connect time. For a true `STARTTLS` flow (start unencrypted, upgrade later) you would open a TCP connection and toggle crypto afterwards — see [Recipe SMTP Client](Recipe-SMTP-Client.md) for an implicit-TLS example, and the [PHP manual](https://www.php.net/manual/en/function.stream-socket-enable-crypto.php) for STARTTLS handling.

`crypto(null)` disables crypto on a stream that previously had it enabled.

### Calling `crypto()` before `connect()`

The client has no stream to toggle yet, so `crypto()` throws `SocketException` if invoked before `connect()`. Configure the desired cipher family via `option('crypto_method', ...)` instead if you need to pin it at connect time.

## Certificate generation (development only)

For local testing you need a self-signed certificate. The fastest way is `openssl`:

```bash
openssl req -x509 -newkey rsa:2048 -nodes -keyout key.pem -out cert.pem \
    -days 365 -subj '/CN=localhost'
cat cert.pem key.pem > server.pem
```

The package's test suite generates them in pure PHP via `openssl_csr_new` / `openssl_csr_sign` — see [Testing Strategy](Testing-Strategy.md) for a reusable snippet. For production, point `option('local_cert', ...)` at a real PEM bundle issued by your CA.

## Common gotchas

### Verifying the peer in production

The development snippets disable `verify_peer` / `verify_peer_name` to accept self-signed certs. **Do not ship that**. In production, set `verify_peer => true` (default) and either `cafile` / `capath` for a custom trust store, or rely on the system CA bundle.

### Server certificate hostname

PHP's `verify_peer_name` matches the certificate's CN / SAN against the **host** argument you passed to `Socket::client(...)`. If you bind to `127.0.0.1` but the cert says `CN=example.com`, verification fails unless you turn off `verify_peer_name`. Prefer hostnames over IPs for TLS endpoints.

### Handshake blocks accept

`stream_socket_accept` blocks until the TLS handshake finishes. Under high fan-in, a slow handshake (or a hostile client that drips bytes) holds up the loop. For production-scale TLS terminate in a reverse proxy (HAProxy / nginx / Caddy) and run plain TCP behind it.

### "no suitable signature algorithm"

OpenSSL 3.x rejects older signature algorithms by default. If you have a legacy server, pin `CryptoMethod::TLSv1_0` or `TLSv1_1` via `crypto()` (and accept the security implications).

## Exception flow

| Error | Exception |
|---|---|
| `stream_socket_server` fails (bind / port in use) | `SocketListenException` |
| `stream_socket_client` fails (refused / handshake error) | `SocketConnectionException` |
| `stream_select` fails | `SocketException` |
| `listen()` twice | `SocketException` |
| `connect()` twice | `SocketException` |
| `crypto()` before `connect()` (client) | `SocketException` |
| `tick()` before `listen()` | `SocketException` |

## See also

- **[Transport SSL](Transport-SSL.md)** — the `ssl://` scheme variant.
- **[SSL Context Options](SSL-Context-Options.md)** — every key `option()` accepts.
- **[Recipe Self Signed TLS](Recipe-Self-Signed-TLS.md)** — generate a cert, bind, connect end-to-end.
- **[Recipe SMTP Client](Recipe-SMTP-Client.md)** — implicit-TLS over `Transport::SSL` against Gmail.
