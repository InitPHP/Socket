# Recipe — Self-Signed TLS

Generate a certificate from PHP, bind a TLS server, and connect with a TLS client — all in the same loopback session. Useful for local development and for the package's own integration tests.

> **Production reminder.** The `verify_peer => false` setup below is for development only. Configure trust with a proper `cafile` / `capath` (or a `peer_fingerprint`) before exposing anything to a real network.

## Generating the cert in pure PHP

`mint-cert.php`:

```php
<?php
$pkey = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
$csr   = openssl_csr_new(['commonName' => 'localhost'], $pkey);
$x509  = openssl_csr_sign($csr, null, $pkey, days: 1);

openssl_x509_export($x509, $certPem);
openssl_pkey_export($pkey, $keyPem);

file_put_contents(__DIR__ . '/server.pem', $certPem . $keyPem);
echo "wrote ", __DIR__, "/server.pem\n";
```

```bash
php mint-cert.php
```

You now have a single PEM file containing both the certificate and its private key. The server points its `local_cert` option at this file.

For a shell-based alternative, `openssl req -x509 …` from the [Transport TLS](Transport-TLS.md) page produces the same shape.

## A complete TLS round-trip (forked)

TLS needs concurrent server and client (the handshake is interactive), so the simplest cross-process example forks. `tls-roundtrip.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$port     = 8443;
$certPath = __DIR__ . '/server.pem';

$pid = pcntl_fork();
if ($pid === -1) {
    fwrite(STDERR, "fork failed\n");
    exit(1);
}

if ($pid === 0) {
    // ── child: the client ────────────────────────────────────
    usleep(150_000);  // give the server a beat to bind
    $client = Socket::client(Transport::TLS, '127.0.0.1', $port, timeout: 2.0)
        ->option('verify_peer',       false)
        ->option('verify_peer_name',  false)
        ->option('allow_self_signed', true);
    $client->connect();
    $client->write("hello tls\n");

    $reply = null;
    for ($i = 0; $i < 200 && $reply === null; ++$i) {
        $reply = $client->read(1024);
        if ($reply === null) usleep(20_000);
    }
    echo "client received: ", trim((string) $reply), PHP_EOL;
    $client->disconnect();
    exit(0);
}

// ── parent: the server ───────────────────────────────────────
$server = Socket::server(Transport::TLS, '127.0.0.1', $port, timeout: 2.0)
    ->option('local_cert',        $certPath)
    ->option('allow_self_signed', true);
$server->listen();

$received = null;
$deadline = microtime(true) + 5.0;
while ($received === null && microtime(true) < $deadline) {
    $server->tick(function ($srv, $conn) use (&$received) {
        $payload = $conn->read(1024);
        if ($payload !== null) {
            $received = $payload;
            $conn->write("echo: " . $payload);
        }
    }, 0.1);
}
$server->close();

pcntl_waitpid($pid, $status);
echo "server received: ", trim((string) $received), PHP_EOL;
```

Run it:

```bash
php tls-roundtrip.php
```

You should see something like:

```
client received: echo: hello tls
server received: hello tls
```

## Same-process testing variant

If your environment does not have `ext-pcntl` (Windows, shared hosting, …) you can still drive the same client/server in tests using the package's `tick()` API. The TLS handshake itself still needs concurrency, so for unit-level coverage you typically:

- Use a plain `tcp://` socket pair to test the abstract stream client's I/O paths (see [Testing Strategy](Testing-Strategy.md)).
- Use [Recipe Custom Channel](Recipe-Custom-Channel.md) to plug an in-memory channel in place of the real network.

## Verifying a real CA instead

When you have a CA-issued cert, the client side simplifies to:

```php
$client = Socket::client(Transport::TLS, 'api.example.com', 443, timeout: 5.0);
// verify_peer = true is the default; PHP uses the system trust store.
$client->connect();
```

For a private CA, point `cafile` at the trust anchor:

```php
$client = Socket::client(Transport::TLS, 'private-api.example.com', 443)
    ->option('cafile', '/etc/myapp/ca-bundle.pem');
```

## See also

- **[Transport TLS](Transport-TLS.md)** — protocol-level reference, handshake notes.
- **[SSL Context Options](SSL-Context-Options.md)** — every key `option()` accepts.
- **[Testing Strategy](Testing-Strategy.md)** — how the package's own test suite drives TLS deterministically.
