# Enums

The package replaces every magic integer / string flag with a PHP enum. Three enums live under `InitPHP\Socket\Enum`.

## `Transport`

Selects the transport for `Socket::server()` / `Socket::client()`.

```php
namespace InitPHP\Socket\Enum;

enum Transport: string
{
    case TCP = 'tcp';
    case UDP = 'udp';
    case TLS = 'tls';
    case SSL = 'ssl';

    public function isStream(): bool;     // TLS or SSL
    public function isDatagram(): bool;   // UDP
    public function scheme(): string;     // value alias for stream URLs
}
```

### Predicates

```php
Transport::TLS->isStream();      // true
Transport::SSL->isStream();      // true
Transport::TCP->isStream();      // false

Transport::UDP->isDatagram();    // true
Transport::TCP->isDatagram();    // false

Transport::TLS->scheme();        // 'tls'  (used as 'tls://host:port')
```

### Picking a case

| Use case | Case |
|---|---|
| Plain TCP server / client | `Transport::TCP` |
| Datagrams | `Transport::UDP` |
| Modern TLS-encrypted streams | `Transport::TLS` |
| Legacy implicit-TLS on `ssl://` (SMTPS 465, IMAPS 993, …) | `Transport::SSL` |

## `Domain`

The address family for TCP / UDP. Ignored for TLS / SSL (the stream wrapper figures it out from the URL).

```php
namespace InitPHP\Socket\Enum;

enum Domain: string
{
    case V4   = 'v4';
    case V6   = 'v6';
    case UNIX = 'unix';

    public function toAddressFamily(): int;
    public static function fromName(?string $name): self;
}
```

| Case | `toAddressFamily()` |
|---|---|
| `Domain::V4` | `AF_INET` |
| `Domain::V6` | `AF_INET6` |
| `Domain::UNIX` | `AF_UNIX` |

`fromName()` is a convenience for parsing from configuration:

```php
Domain::fromName(null);       // Domain::V4   (null and '' default to V4)
Domain::fromName('');         // Domain::V4
Domain::fromName('v6');       // Domain::V6
Domain::fromName('V6');       // Domain::V6   (case-insensitive)
Domain::fromName('unix');     // Domain::UNIX
Domain::fromName('ipx');      // SocketInvalidArgumentException
```

## `CryptoMethod`

A pinned TLS / SSL protocol version. Maps to PHP's `STREAM_CRYPTO_METHOD_*_CLIENT` and `STREAM_CRYPTO_METHOD_*_SERVER` constants.

```php
namespace InitPHP\Socket\Enum;

enum CryptoMethod: string
{
    case SSLv2   = 'sslv2';
    case SSLv3   = 'sslv3';
    case SSLv23  = 'sslv23';
    case ANY     = 'any';
    case TLS     = 'tls';
    case TLSv1_0 = 'tlsv1.0';
    case TLSv1_1 = 'tlsv1.1';
    case TLSv1_2 = 'tlsv1.2';

    public function forClient(): int;
    public function forServer(): int;
    public static function fromName(string $name): self;
}
```

### Picking the right method

Prefer the highest version your peers support. **`CryptoMethod::TLSv1_2`** is the safe modern default; only fall back to lower versions if you have a specific legacy peer.

```php
$client->crypto(CryptoMethod::TLSv1_2);
$server->crypto(CryptoMethod::TLSv1_2);
```

### `fromName()`

Convenience for parsing from configuration. Case-insensitive:

```php
CryptoMethod::fromName('TLSv1.2');   // CryptoMethod::TLSv1_2
CryptoMethod::fromName('tlsv1.2');   // CryptoMethod::TLSv1_2
CryptoMethod::fromName('tlsv9');     // SocketInvalidArgumentException
```

### Server vs client constants

PHP exposes separate constants for the server and client ends:

```php
CryptoMethod::TLSv1_2->forClient();   // STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
CryptoMethod::TLSv1_2->forServer();   // STREAM_CRYPTO_METHOD_TLSv1_2_SERVER
```

The `AbstractStreamServer::crypto()` and `AbstractStreamClient::crypto()` setters pick the right side automatically — you only ever pass the enum case.

## See also

- **[Transport TLS](Transport-TLS.md)** — when to call `crypto()`.
- **[SSL Context Options](SSL-Context-Options.md)** — the `option()` keys that work with these enums.
- **[API Reference](API-Reference.md)** — full surface in one page.
