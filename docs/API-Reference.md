# API Reference

A single-page index of every public type. For the deeper "why", see the linked concept pages.

## Factory

### `InitPHP\Socket\Socket`

```php
final class Socket
{
    public static function server(
        Transport $transport,
        string $host,
        int $port,
        ?Domain $domain = null,
        ?float $timeout = null,
    ): SocketServerInterface;

    public static function client(
        Transport $transport,
        string $host,
        int $port,
        ?Domain $domain = null,
        ?float $timeout = null,
    ): SocketClientInterface;
}
```

`Domain` is honoured only by TCP / UDP. `$timeout` is honoured only by TLS / SSL.

## Server contracts

### `InitPHP\Socket\Interfaces\SocketServerInterface`

```php
interface SocketServerInterface
{
    public function getHost(): string;
    public function getPort(): int;
    public function getSocket(): mixed;          // \Socket | resource | null

    /** @return array<int|string, SocketConnectionInterface> */
    public function getClients(): array;

    public function listen(): static;
    public function close(): bool;

    public function live(callable $callback, float $idleSeconds = 0.05): void;
    public function tick(callable $callback, float $waitSeconds = 0.0): int;
    public function stop(): void;
    public function isRunning(): bool;

    /** @param int|string|array<int, int|string>|null $clients */
    public function broadcast(string $message, int|string|array|null $clients = null): bool;
    public function register(int|string $id, SocketConnectionInterface $client): bool;

    public function wait(float $seconds): void;
}
```

### `InitPHP\Socket\Server\AbstractServer`

Adds:

```php
abstract class AbstractServer implements SocketServerInterface
{
    protected function addClient(SocketConnectionInterface $client): int;   // returns the internal key
    protected function evict(int $key): void;
    protected function indexOf(SocketConnectionInterface $client): ?int;
    protected static function splitSeconds(float $seconds): array;          // [whole, microseconds]
}
```

### `InitPHP\Socket\Server\TCP` / `UDP`

Concrete `ext-sockets`-backed servers. `TCP` additionally exposes:

```php
public function backlog(int $backlog): self;   // default 8, must be >= 1
```

### `InitPHP\Socket\Server\AbstractStreamServer`

The parent of `TLS` and `SSL`. Adds:

```php
public function option(string $key, mixed $value): static;
public function timeout(float $seconds): static;
public function blocking(bool $mode = true): static;
public function crypto(?CryptoMethod $method): static;
```

`TLS` and `SSL` themselves carry no extra public surface — see [Transport TLS](Transport-TLS.md) / [Transport SSL](Transport-SSL.md).

## Client contracts

### `InitPHP\Socket\Interfaces\SocketClientInterface`

```php
interface SocketClientInterface
{
    public function getHost(): string;
    public function getPort(): int;
    public function getSocket(): mixed;

    public function connect(): static;
    public function disconnect(): bool;

    public function read(int $length = 1024): ?string;
    public function write(string $data): ?int;
}
```

### `InitPHP\Socket\Client\TCP`

```php
public function read(int $length = 1024, int $type = PHP_BINARY_READ): ?string;
```

(`$type` accepts `PHP_BINARY_READ` or `PHP_NORMAL_READ`.)

### `InitPHP\Socket\Client\UDP`

```php
public function read(int $length = 1024, int $flags = 0): ?string;
public function write(string $data, int $flags = 0): ?int;
```

(`$flags` accepts the standard `MSG_*` bitmasks.)

### `InitPHP\Socket\Client\AbstractStreamClient`

The parent of `Client\TLS` and `Client\SSL`. Adds:

```php
public function option(string $key, mixed $value): static;
public function timeout(float $seconds): static;
public function blocking(bool $mode = true): static;
public function crypto(?CryptoMethod $method): static;
```

## Per-connection contract

### `InitPHP\Socket\Interfaces\SocketConnectionInterface`

```php
interface SocketConnectionInterface
{
    public function setId(int|string $id): static;
    public function getId(): int|string|null;

    public function read(int $length = 1024): ?string;
    public function write(string $data): ?int;
    public function close(): bool;
    public function isAlive(): bool;

    public function getSocket(): mixed;
    public function getChannel(): ChannelInterface;
}
```

The only implementation in the package is `InitPHP\Socket\Server\ServerConnection`.

## Channel contract

### `InitPHP\Socket\Interfaces\ChannelInterface`

```php
interface ChannelInterface
{
    public function read(int $length = 1024, ?int $flag = null): ?string;
    public function write(string $data): ?int;
    public function close(): bool;
    public function isAlive(): bool;
    public function getResource(): mixed;
}
```

Built-in implementations:

| Class | Backing | Notes |
|---|---|---|
| `Channel\TcpChannel` | `ext-sockets` | Liveness via `MSG_PEEK | MSG_DONTWAIT`. |
| `Channel\UdpChannel` | `ext-sockets` (shared listening socket) | Per-peer buffer drained by `read()`. Exposes `feed()`, `getPeerHost()`, `getPeerPort()`, `peerKey()`. |
| `Channel\StreamChannel` | stream resource | Liveness via `feof()`. |

## Enums

See [Enums](Enums.md) for the full reference.

```php
InitPHP\Socket\Enum\Transport       // TCP, UDP, TLS, SSL
InitPHP\Socket\Enum\Domain          // V4, V6, UNIX
InitPHP\Socket\Enum\CryptoMethod    // SSLv2/3/23, ANY, TLS, TLSv1_0/1_1/1_2
```

## Exceptions

See [Exceptions](Exceptions.md) for the full reference.

```php
InitPHP\Socket\Exception\SocketExceptionInterface    // marker
InitPHP\Socket\Exception\SocketException             // \RuntimeException
InitPHP\Socket\Exception\SocketConnectionException   // \RuntimeException
InitPHP\Socket\Exception\SocketListenException       // \RuntimeException
InitPHP\Socket\Exception\SocketInvalidArgumentException  // \InvalidArgumentException
```

## See also

- **[Architecture](Architecture.md)** — what each type does and why.
- **[Server Lifecycle](Server-Lifecycle.md)** / **[Client Lifecycle](Client-Lifecycle.md)** — when to call each method.
