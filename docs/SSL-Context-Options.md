# SSL Context Options

The TLS and SSL classes wrap PHP's [SSL context](https://www.php.net/manual/en/context.ssl.php). The fluent `option(string $key, mixed $value)` method writes into the underlying `['ssl' => …]` array passed to `stream_context_create`.

This page summarises the keys you reach for most often. The PHP manual is the authoritative reference.

## Server-side keys

| Key | Type | Purpose |
|---|---|---|
| `local_cert` | `string` (path) | PEM file containing the server certificate (and optionally its private key). Required. |
| `local_pk` | `string` (path) | PEM file with the private key, if not bundled with `local_cert`. |
| `passphrase` | `string` | Passphrase protecting the private key. |
| `cafile` | `string` (path) | CA bundle used when verifying client certificates (mutual TLS). |
| `capath` | `string` (dir) | Hashed CA directory; alternative to `cafile`. |
| `verify_peer` | `bool` (default `true`) | Verify the peer certificate. Leave `true` for mutual TLS. |
| `verify_peer_name` | `bool` (default `true`) | Match the peer cert CN/SAN against the connecting peer name. |
| `allow_self_signed` | `bool` (default `false`) | Accept self-signed certs (development only). |
| `crypto_method` | `int` (`STREAM_CRYPTO_METHOD_*_SERVER`) | Pin a specific cipher family. Prefer `crypto(CryptoMethod::TLSv1_2)`. |
| `ciphers` | `string` | OpenSSL cipher list. |
| `single_dh_use` | `bool` | Use a fresh Diffie-Hellman key for every TLS session. |
| `dh_param` | `string` (path) | Custom DH parameters PEM file. |
| `disable_compression` | `bool` (default `true` since PHP 7) | Disable TLS compression (CRIME mitigation). |
| `peer_fingerprint` | `string|array` | Optional sha256 fingerprint of an expected client cert. |
| `SNI_enabled` | `bool` (default `true`) | Honour Server Name Indication from the client. |

### Minimal server config

```php
$server = Socket::server(Transport::TLS, '0.0.0.0', 8443, timeout: 5.0)
    ->option('local_cert', '/etc/myapp/server.pem');
```

### Mutual-TLS (client-cert) server

```php
$server = Socket::server(Transport::TLS, '0.0.0.0', 8443)
    ->option('local_cert',       '/etc/myapp/server.pem')
    ->option('verify_peer',      true)
    ->option('cafile',           '/etc/myapp/ca-bundle.pem')
    ->option('verify_peer_name', true);
```

## Client-side keys

| Key | Type | Purpose |
|---|---|---|
| `cafile` | `string` (path) | Trust anchor for verifying the server cert. |
| `capath` | `string` (dir) | Hashed CA directory. |
| `verify_peer` | `bool` (default `true`) | Verify the peer cert. **Keep on in production.** |
| `verify_peer_name` | `bool` (default `true`) | Match cert CN/SAN against the `host` you connected to. |
| `allow_self_signed` | `bool` (default `false`) | Accept self-signed certs (dev / test only). |
| `peer_name` | `string` | Override the name used for `verify_peer_name` matching. Useful when the target hostname differs from the cert CN. |
| `peer_fingerprint` | `string|array` | Expected SHA-256 fingerprint of the server cert (cert pinning). |
| `SNI_enabled` | `bool` (default `true`) | Send Server Name Indication during the handshake. |
| `local_cert` / `local_pk` / `passphrase` | as on server | Client certificate for mutual TLS. |
| `crypto_method` | `int` (`STREAM_CRYPTO_METHOD_*_CLIENT`) | Pin a specific protocol version. Prefer `crypto(CryptoMethod::TLSv1_2)`. |
| `ciphers` | `string` | OpenSSL cipher list. |
| `disable_compression` | `bool` (default `true`) | Disable TLS compression. |

### Verifying with system CAs

```php
$client = Socket::client(Transport::TLS, 'example.com', 443, timeout: 5.0);
// PHP will use the OS trust store by default; explicit cafile is only needed
// when you want a custom root.
```

### Self-signed development setup

```php
$client = Socket::client(Transport::TLS, '127.0.0.1', 8443)
    ->option('verify_peer',       false)
    ->option('verify_peer_name',  false)
    ->option('allow_self_signed', true);
```

> **Never ship this configuration.** It is exactly the shape of "accept any certificate", which defeats TLS. Pin a `cafile` or `peer_fingerprint` in production.

### Mutual-TLS client (client certificate)

```php
$client = Socket::client(Transport::TLS, 'api.example.com', 443)
    ->option('local_cert', '/etc/myapp/client.pem')
    ->option('local_pk',   '/etc/myapp/client.key')
    ->option('passphrase', getenv('CLIENT_KEY_PASS'));
```

## Setting crypto_method via the enum

Two equivalent ways to pin TLS 1.2:

```php
// Fluent helper (preferred)
$server->crypto(CryptoMethod::TLSv1_2);
$client->crypto(CryptoMethod::TLSv1_2);

// Raw context option
$server->option('crypto_method', STREAM_CRYPTO_METHOD_TLSv1_2_SERVER);
$client->option('crypto_method', STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
```

`crypto()` automatically picks the right constant for the side you're on.

## Where these options end up

Every `option()` call goes into the array used to build the SSL context:

```php
// roughly what the package does internally
$context = stream_context_create([
    'ssl' => $this->options,   // everything you set via option()
]);
$stream = stream_socket_client($url, $errNo, $errStr, $timeout, STREAM_CLIENT_CONNECT, $context);
```

If a key is not listed above, it still works — `option()` is a pass-through. Anything PHP's SSL context accepts is accepted here.

## See also

- **[Transport TLS](Transport-TLS.md)** — `tls://` scheme details.
- **[Transport SSL](Transport-SSL.md)** — `ssl://` scheme details (same options, different defaults).
- **[Enums](Enums.md)** — `CryptoMethod`.
- [PHP manual: SSL context options](https://www.php.net/manual/en/context.ssl.php)
