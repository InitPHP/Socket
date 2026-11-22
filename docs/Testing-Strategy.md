# Testing Strategy

The package's own test suite is unit + integration with no mocking framework. This page documents the patterns it uses, so you can extend the suite with the same shapes or borrow them for your own server / client code.

## How the package's suite is organised

```
tests/
├─ Unit/                                # no I/O, no real sockets
│  ├─ Channel/         StreamChannelTest, TcpChannelTest, UdpChannelTest
│  ├─ Client/          AbstractClientTest, AbstractStreamClientTest
│  ├─ Enum/            TransportTest, DomainTest, CryptoMethodTest
│  ├─ Exception/       HierarchyTest
│  ├─ Server/          AbstractServerBroadcastTest, AbstractStreamServerTest,
│  │                   ServerConnectionTest, TcpServerOptionsTest,
│  │                   UdpServerOptionsTest
│  └─ SocketFactoryTest
└─ Integration/                          # real loopback sockets
   ├─ IntegrationTestCase.php            # shared helpers
   ├─ ConnectionErrorsTest.php
   ├─ ServerLifecycleTest.php
   ├─ StreamClientIoTest.php
   ├─ TcpDisconnectTest.php
   ├─ TcpEchoTest.php
   ├─ TcpServerCloseTest.php
   ├─ TlsEchoTest.php                    # forks the client into a child process
   ├─ UdpClientReuseTest.php
   ├─ UdpEchoTest.php
   └─ UdpServerCloseTest.php
```

Numbers as of the 2.0 release: **96 tests · 357 assertions · ≥ 90% line coverage**.

## Running it

```bash
composer install
composer test                # PHPUnit, unit + integration

composer test-coverage       # same, with Clover output for CI

composer qa                  # cs-check + stan + test
```

`composer test-coverage` needs **pcov** (or Xdebug). Install pcov via PECL:

```bash
pecl install pcov            # may need brew install pcre2 on macOS for headers
```

## Unit patterns

### Fake channel for in-memory testing

The `ServerConnection` class is just identity + delegation, so a `FakeChannel` is enough to exercise broadcasting, registry mapping and lifecycle logic:

```php
final class FakeChannel implements ChannelInterface
{
    public ?string $readReturn = null;
    public ?int    $writeReturn = null;
    public bool    $alive = true;
    public bool    $closed = false;

    /** @var array<int, array{0:int, 1:?int}> */ public array $readCalls = [];
    /** @var array<int, array{0:string}>      */ public array $writeCalls = [];

    public function read(int $length = 1024, ?int $flag = null): ?string
    { $this->readCalls[] = [$length, $flag]; return $this->readReturn; }

    public function write(string $data): ?int
    { $this->writeCalls[] = [$data]; return $this->writeReturn; }

    public function close(): bool { $this->closed = true; return true; }
    public function isAlive(): bool { return $this->alive && !$this->closed; }
    public function getResource(): mixed { return null; }
}
```

Then assert against `writeCalls` / `readCalls` in tests. No fork, no port, no race.

### Subclassing `AbstractServer` for protected-method coverage

`addClient()` / `evict()` are protected. The unit test surfaces them via a friend subclass that lives in the same file:

```php
final class TestableServer extends AbstractServer
{
    public function attach(SocketConnectionInterface $client): int  { return $this->addClient($client); }
    public function forceEvict(int $key): void                       { $this->evict($key); }

    public function listen(): static                                  { return $this; }
    public function close(): bool                                     { return true; }
    public function tick(callable $callback, float $waitSeconds = 0.0): int { return 0; }
    public function getSocket(): mixed                                { return null; }
}
```

This lets you exercise `broadcast()` / `register()` / `getClients()` without any real network.

### Socket pair for channel I/O

For `TcpChannel`, a `socket_create_pair` gives you two connected, real sockets — perfect for testing reads, writes and `MSG_PEEK`-based liveness without standing up a server:

```php
$pair = [];
socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair);
[$a, $b] = $pair;
socket_set_nonblock($a);
socket_set_nonblock($b);

$channel = new TcpChannel($a);
$channel->write('hi');

$buf = '';
socket_recv($b, $buf, 1024, MSG_DONTWAIT);
self::assertSame('hi', $buf);
```

For `StreamChannel`, `fopen('php://memory', 'r+')` is enough.

## Integration patterns

### Ephemeral port via probe

Tests pick a free port by binding to `0` and reading the assigned port back:

```php
protected function findFreePort(int $type = SOCK_STREAM): int
{
    $proto = $type === SOCK_STREAM ? SOL_TCP : SOL_UDP;
    $sock = socket_create(AF_INET, $type, $proto);
    socket_bind($sock, '127.0.0.1', 0);
    $addr = ''; $port = 0;
    socket_getsockname($sock, $addr, $port);
    socket_close($sock);
    return $port;
}
```

There is a tiny race between this call and the caller's bind, acceptable for loopback test scaffolding.

### Same-process TCP / UDP round-trip

TCP and UDP connect over loopback complete at the kernel level, so the test process can be both client and server. Drive the server with `tick()` between client actions:

```php
$server = new TcpServer('127.0.0.1', $port); $server->listen();
$client = new TcpClient('127.0.0.1', $port); $client->connect();

$server->tick(fn() => null, 0.2);                // accept the queued client

$client->write('hello');
$server->tick(function ($srv, $conn) use (&$received) {
    $received = $conn->read(1024);
    $conn->write('echo:' . $received);
}, 0.2);

self::assertSame('hello', $received);
```

### TLS — fork only the client

The TLS handshake needs concurrency. Coverage tools live in one process, so the **server stays in the parent** and the **client is forked** into a child:

```php
$pid = pcntl_fork();
if ($pid === 0) {
    usleep(150_000);
    $client = Socket::client(Transport::TLS, '127.0.0.1', $port, 2.0)
        ->option('verify_peer', false)
        ->option('allow_self_signed', true);
    $client->connect();
    $client->write('hello-tls');
    // ... read echo ...
    $client->disconnect();
    exit(0);
}

$server = Socket::server(Transport::TLS, '127.0.0.1', $port, 2.0)
    ->option('local_cert', $certPath)
    ->option('allow_self_signed', true);
$server->listen();

while ($received === null && microtime(true) < $deadline) {
    $server->tick($echoCallback, 0.1);
}
pcntl_waitpid($pid, $status);
```

The package's `TlsEchoTest` is exactly this. Coverage attributes every server line because the server runs in the parent (the PHPUnit process).

### Self-signed cert as a test helper

The package's `IntegrationTestCase` ships a `selfSignedCertPath()` helper that mints a fresh cert into a temp file and registers it for cleanup:

```php
$pkey  = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$csr   = openssl_csr_new(['commonName' => 'localhost'], $pkey);
$x509  = openssl_csr_sign($csr, null, $pkey, 1);
openssl_x509_export($x509, $certPem);
openssl_pkey_export($pkey, $keyPem);

$path = tempnam(sys_get_temp_dir(), 'initphp-socket-tls-') . '.pem';
file_put_contents($path, $certPem . $keyPem);
```

## Plain-TCP coverage for `AbstractStreamClient`

You can exercise the entire stream-client code path (connect, read, write, timeout, blocking, crypto-toggle) **without TLS** by talking to a `tcp://` stream server and using a tiny subclass of `AbstractStreamClient`:

```php
final class PlainStreamClient extends AbstractStreamClient
{
    public function __construct(string $host, int $port, ?float $timeout = null)
    {
        parent::__construct($host, $port, Transport::TCP, $timeout);
    }
}

$server = stream_socket_server("tcp://127.0.0.1:{$port}");
$client = new PlainStreamClient('127.0.0.1', $port);
$client->connect();
$peer = stream_socket_accept($server, 1.0);

$client->write('hello');
self::assertSame('hello', fread($peer, 1024));
```

No fork, no certs, no TLS handshake — just the stream-client I/O paths.

## Coverage targets

The CI workflow runs `composer test-coverage` with pcov on PHP 8.3 and uploads the Clover report to Codecov. The 2.0 release ships at:

| Class | Lines |
|---|---|
| `Channel\StreamChannel` | 100% |
| `Channel\TcpChannel` | 100% |
| `Channel\UdpChannel` | 95% |
| `Client\AbstractClient` | 100% |
| `Client\AbstractStreamClient` | 98.5% |
| `Client\TCP` | 93.1% |
| `Client\TLS` / `SSL` | 100% |
| `Client\UDP` | 90% |
| `Enum\*` | 100% |
| `Server\AbstractServer` | 88.1% |
| `Server\AbstractStreamServer` | 86.3% |
| `Server\ServerConnection` | 100% |
| `Server\TCP` | ~85% |
| `Server\UDP` | ~85% |
| `Socket` (factory) | 88.9% |

Overall: **≥ 90% line coverage**. The uncovered lines are mostly OS-call error paths (`socket_create` returning `false`, `socket_select` errors other than `EINTR`, …) that can't be triggered deterministically without mocking the socket extension.

## See also

- **[Event Loop Integration](Event-Loop-Integration.md)** — `tick()` is what makes deterministic tests possible.
- **[Recipe Custom Channel](Recipe-Custom-Channel.md)** — fakes that look like real channels.
- **[Connection and Channel](Connection-and-Channel.md)** — what a `ServerConnection` actually exposes to test code.
