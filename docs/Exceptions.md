# Exceptions

The 2.x release introduced a marker interface so a single `catch` covers every failure mode in the package.

## Hierarchy

```
SocketExceptionInterface  (marker — extends \Throwable)
├─ SocketException                  (extends \RuntimeException)
│  ├─ SocketConnectionException
│  └─ SocketListenException
└─ SocketInvalidArgumentException   (extends \InvalidArgumentException)
```

All four concrete classes live under `InitPHP\Socket\Exception`.

## `SocketExceptionInterface`

A marker — no methods of its own. Catch it to handle every socket failure in one place:

```php
use InitPHP\Socket\Exception\SocketExceptionInterface;

try {
    $server->listen();
    $client->connect();
} catch (SocketExceptionInterface $e) {
    // bind failed, connect refused, invalid argument — anything from the package
    $logger->error('socket boom', ['err' => $e->getMessage()]);
}
```

Because the interface extends `\Throwable`, IDEs and static analysers understand it as a real exception type.

## `SocketException`

The runtime base. Thrown when something failed at the network layer that is not an explicit "could not connect" / "could not listen" / "invalid argument". Extends `\RuntimeException`.

Common situations:

- `socket_create` returned `false`.
- `socket_bind` failed (port in use, permission denied, address not available).
- `socket_select` / `stream_select` reported an error other than `EINTR`.
- `listen()` called twice.
- `connect()` called twice on a client.
- `tick()` called before `listen()`.
- `crypto()` called before `connect()` on a TLS / SSL client.

```php
try {
    $server->listen();
    $server->listen();   // second call
} catch (\InitPHP\Socket\Exception\SocketException $e) {
    // "Server is already listening."
}
```

## `SocketConnectionException`

Subclass of `SocketException`. Thrown when an outbound connection fails:

- TCP `socket_connect` refused (no listener, network unreachable, …).
- UDP `socket_connect` refused (rare — usually an `EACCES`-class error).
- TLS / SSL `stream_socket_client` failed (handshake error, certificate verification, timeout).
- Server-side `socket_accept` errored (non-`EINTR`).

```php
try {
    $client->connect();
} catch (\InitPHP\Socket\Exception\SocketConnectionException $e) {
    // "Socket Connection Error : Connection refused"
}
```

The exception message includes PHP's underlying error string.

## `SocketListenException`

Subclass of `SocketException`. Specific to bind / listen failures on the server side:

- `socket_listen` failed.
- `stream_socket_server` failed (TLS / SSL bind).

```php
try {
    $server->listen();
} catch (\InitPHP\Socket\Exception\SocketListenException $e) {
    // "stream_socket_server failed (98): Address already in use"
}
```

The split between `SocketException` (bind failure) and `SocketListenException` (listen failure) mirrors PHP's two-step API. In practice most callers catch the more general `SocketException`.

## `SocketInvalidArgumentException`

Extends `\InvalidArgumentException` **and** implements `SocketExceptionInterface`. Thrown synchronously by constructors and configuration setters when the input is wrong:

- Empty host.
- Port outside `1‒65535`.
- `Domain::fromName('ipx')` — unknown domain string.
- `CryptoMethod::fromName('tlsv9')` — unknown crypto method.
- `TCP\backlog(-1)` — non-positive backlog.
- `StreamChannel::__construct($notAResource)` — handed a non-resource.

Catch either the native parent or the marker interface, depending on whether you want package-only granularity:

```php
try {
    Socket::server(Transport::TCP, '', 80);
} catch (\InvalidArgumentException $e) {           // generic
    // ...
} catch (SocketExceptionInterface $e) {            // package-only
    // ...
}
```

## Error-message conventions

Every package message starts with the PHP-level call that failed when possible, so the cause is searchable:

```
"socket_bind failed: Address already in use"
"stream_socket_client failed (61): Connection refused"
"Server is already listening."
"backlog must be at least 1."
```

If a system call returned a numeric errno, the format is `(errno) description`. For PHP-level guards the message is a plain English sentence.

## Catching strategy

| Goal | Catch |
|---|---|
| Anything from the package | `SocketExceptionInterface` |
| Runtime failures only (bind / listen / connect / select) | `SocketException` |
| "Could not connect to peer" | `SocketConnectionException` |
| "Could not bind / listen on port" | `SocketListenException` |
| "Caller passed something invalid" | `SocketInvalidArgumentException` (or generic `\InvalidArgumentException`) |

## See also

- **[Server Lifecycle](Server-Lifecycle.md)** — which methods throw which exception.
- **[Client Lifecycle](Client-Lifecycle.md)** — symmetric story for clients.
- **[FAQ](FAQ.md)** — common error messages and what they mean.
