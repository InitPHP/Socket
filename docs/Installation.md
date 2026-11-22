# Installation

## Composer

```bash
composer require initphp/socket
```

The package follows [Semantic Versioning](https://semver.org/). The current major is **3.x**.

```json
{
    "require": {
        "initphp/socket": "^3.0"
    }
}
```

## Requirements

| Requirement | Version | Why |
|---|---|---|
| **PHP** | `^8.2` | The package uses enums, `readonly` promoted properties, intersection types and other modern PHP features. |
| **ext-sockets** | bundled in PHP | Backs the TCP and UDP transports (`socket_create`, `socket_bind`, …). |
| **ext-openssl** | bundled in PHP | Powers the `tls://` and `ssl://` stream wrappers. |

> **Heads-up:** `ext-sockets` ships with PHP but is occasionally disabled in shared hosting builds. Run `php -m | grep sockets` if you are not sure.

## Optional extensions

| Extension | Purpose |
|---|---|
| **ext-pcntl** | Required only to run the package's TLS integration test suite (the test forks a child process for the client side of the handshake). Production code never depends on it. |
| **ext-pcov** *or* **ext-xdebug** | Code coverage when running the test suite. CI uses pcov for speed. |

## Verifying the install

A one-liner that exercises both the factory and the runtime:

```php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$server = Socket::server(Transport::TCP, '127.0.0.1', 9000);
echo get_class($server), PHP_EOL;
// InitPHP\Socket\Server\TCP
```

If that prints the class name, your install is good. No socket was bound; the factory returns a configured-but-passive server.

## Next steps

- Run the [Quick Start](Quick-Start.md) for an end-to-end echo example.
- Skim the [Architecture](Architecture.md) page if you want to understand how the pieces fit together before reading the API.
