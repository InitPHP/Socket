# Client Lifecycle

A client has three states. The transitions are short — clients are simpler than servers because they own a single peer.

```
constructed ──connect()──► connected ──disconnect()──► closed
```

## 1. Construct

```php
use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\{Transport, Domain};

$client = Socket::client(Transport::TCP, '127.0.0.1', 9000, Domain::V4);
```

What happens:

- Host and port are validated synchronously. Empty host or out-of-range port throws `SocketInvalidArgumentException`.
- For TLS / SSL, the optional `$timeout` is stored and used by `connect()`.
- No network call has been made yet.

## 2. Connect

```php
$client->connect();
```

- **TCP / UDP** — `socket_create` then `socket_connect`. UDP "connect" just locks the peer locally; there is no datagram round-trip.
- **TLS / SSL** — `stream_socket_client` with an SSL context built from `option()` calls. The TLS handshake completes inside this call.

If anything goes wrong (the peer is not listening, the certificate fails verification, the handshake times out), `connect()` throws `SocketConnectionException`. The message contains PHP's underlying errno and description.

```php
try {
    $client->connect();
} catch (\InitPHP\Socket\Exception\SocketConnectionException $e) {
    // retry, fall back, log, …
}
```

Calling `connect()` a second time **throws** `SocketException` — clients are single-use, not reusable. Construct a fresh instance to retry.

## 3. Read and write

```php
$bytes = $client->write("PING\r\n");   // returns int bytes written, or null on failure
$reply = $client->read(1024);          // returns ?string, null = no data / failure
```

Both `read()` and `write()` return `null` on failure rather than raising. This makes happy-path code uncluttered while still leaving room for error handling:

```php
$reply = $client->read(1024);
if ($reply === null) {
    // EOF, error, or non-blocking read with nothing yet
}
```

### Transport-specific extras

- **TCP** — `read(int $length = 1024, int $type = PHP_BINARY_READ)`. Pass `PHP_NORMAL_READ` to read line-by-line.
- **UDP** — `read(int $length = 1024, int $flags = 0)` and `write(string $data, int $flags = 0)` accept the standard `MSG_*` flags.
- **TLS / SSL** — `option()`, `timeout()`, `blocking()` and `crypto()` chainables on top of the base API.

## 4. Disconnect

```php
$client->disconnect();
```

- TCP / UDP — `socket_close`.
- TLS / SSL — `fclose` on the stream.

`disconnect()` is **idempotent** — call it multiple times, call it before `connect()`, both are fine and return `true`.

After `disconnect()`:

- `getSocket()` returns `null`.
- `read()` / `write()` return `null`.
- `connect()` is not allowed again (it would refuse on the "already connected" check earlier in the lifecycle of a fresh instance; here the contract is "this instance is done").

## TLS / SSL configuration

The TLS / SSL clients add fluent setters that mirror the SSL stream context (see [SSL Context Options](SSL-Context-Options.md)):

```php
$client = Socket::client(Transport::TLS, 'example.com', 443, timeout: 5.0)
    ->option('verify_peer', true)
    ->option('verify_peer_name', true)
    ->option('cafile', '/etc/ssl/certs/ca-certificates.crt')
    ->option('SNI_enabled', true)
    ->timeout(2.0)
    ->blocking(true);

$client->connect();
```

`crypto()` can be used **after** `connect()` to enable / disable encryption on the fly — useful for protocols that start unencrypted and `STARTTLS` later:

```php
use InitPHP\Socket\Enum\CryptoMethod;

$client = Socket::client(Transport::TLS, 'mail.example.com', 587);
$client->connect();
// EHLO, STARTTLS, …
$client->crypto(CryptoMethod::TLSv1_2);  // upgrade
```

Calling `crypto()` **before** `connect()` throws `SocketException` (there is no stream to toggle yet).

## Method matrix

| Method | Before `connect()` | After `connect()` | After `disconnect()` |
|---|---|---|---|
| `connect()` | opens the connection | **throws** | **throws** |
| `disconnect()` | no-op (returns `true`) | closes | no-op |
| `read()` | returns `null` | reads | returns `null` |
| `write()` | returns `null` | writes | returns `null` |
| `getSocket()` | `null` | live handle | `null` |
| `getHost()` / `getPort()` | configured | configured | configured |
| `option()` (TLS / SSL) | stores in context | stores in context | stores in context |
| `timeout()` (TLS / SSL) | stores | stores **and** applies to live stream | stores |
| `blocking()` (TLS / SSL) | stores | stores **and** applies to live stream | stores |
| `crypto()` (TLS / SSL) | **throws** | toggles | depends — usually unsafe |

## See also

- **[Server Lifecycle](Server-Lifecycle.md)** — symmetric story for the accept side.
- **[Transport TCP](Transport-TCP.md)** / **[Transport UDP](Transport-UDP.md)** — flag arguments on `read()` / `write()`.
- **[Transport TLS](Transport-TLS.md)** — TLS handshake specifics.
- **[SSL Context Options](SSL-Context-Options.md)** — every key `option()` accepts.
