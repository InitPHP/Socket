# InitPHP Socket Manager

PHP Socket (TCP, TLS, UDP, SSL) Server/Client Library

[![Latest Stable Version](http://poser.pugx.org/initphp/socket/v)](https://packagist.org/packages/initphp/socket) [![Total Downloads](http://poser.pugx.org/initphp/socket/downloads)](https://packagist.org/packages/initphp/socket) [![Latest Unstable Version](http://poser.pugx.org/initphp/socket/v/unstable)](https://packagist.org/packages/initphp/socket) [![License](http://poser.pugx.org/initphp/socket/license)](https://packagist.org/packages/initphp/socket) [![PHP Version Require](http://poser.pugx.org/initphp/socket/require/php)](https://packagist.org/packages/initphp/socket)

## Requirements

- PHP 7.4 or higher
- PHP Sockets Extension

## Installation

```
composer require initphp/socket
```

## Usage

**Supported Types :**

- TCP
- UDP
- TLS
- SSL

### Factory

`\InitPHP\Socket\Socket::class` It allows you to easily create socket server or client.

#### `Socket::server()`

```php 
public static function server(int $handler = Socket::TCP, string $host = '', int $port = 0, null|string|float $argument = null): \InitPHP\Socket\Interfaces\SocketServerInterface
```

- `$handler` : `Socket::SSL`, `Socket::TCP`, `Socket::TLS` or `Socket::UDP`
- `$host` : Identifies the socket host. If not defined or left blank, it will throw an error.
- `$port` : Identifies the socket port. If not defined or left blank, it will throw an error.
- `$argument` : This value is the value that will be sent as 3 parameters to the constructor method of the handler.
    - SSL or TLS = (float) Defines the timeout period.
    - UDP or TCP = (string) Defines the protocol family to be used by the socket. "v4", "v6" or "unix"

#### `Socket::client()`

```php 
public static function client(int $handler = self::TCP, string $host = '', int $port = 0, null|string|float $argument = null): \InitPHP\Socket\Interfaces\SocketClientInterface
```

- `$handler` : `Socket::SSL`, `Socket::TCP`, `Socket::TLS` or `Socket::UDP`
- `$host` : Identifies the socket host. If not defined or left blank, it will throw an error.
- `$port` : Identifies the socket port. If not defined or left blank, it will throw an error.
- `$argument` : This value is the value that will be sent as 3 parameters to the constructor method of the handler.
    - SSL or TLS = (float) Defines the timeout period.
    - UDP or TCP = (string) Defines the protocol family to be used by the socket. "v4", "v6" or "unix"

### Methods

**`connection()` :** Initiates the socket connection.

```php 
public function connection(): self;
```

**`disconnect()` :** Terminates the connection.

```php 
public function disconnect(): bool;
```

**`read()` :** Reads data from socket.

```php 
public function read(int $length = 1024): ?string;
```

**`write()` :** Writes data to the socket

```php 
public function write(string $string): ?int;
```

#### Server Methods

**`live()` :**

```php 
public function live(callable $callback): void;
```

**`wait()` :**

```php 
public function wait(int $second): void;
```

#### Special methods for TLS and SSL.

TLS and SSL work similarly.

There are some additional methods you can use from TLS and SSL sockets.

**`timeout()` :** Defines the timeout period of the current.

```php
public function timeout(int $second): self;
```

**`blocking()` :** Sets the blocking mode of the current.

```php
public function blocking(bool $mode = true): self;
```

**`crypto()` :** Turns encryption on or off on a connected socket.

```php
public function crypto(?string $method = null): self;
```

Possible values for `$method` are;

- "sslv2"
- "sslv3"
- "sslv23"
- "any"
- "tls"
- "tlsv1.0"
- "tlsv1.1"
- "tlsv1.2"
- NULL

**`option()` :** Defines connection options for SSL and TLS. see; [https://www.php.net/manual/en/context.ssl.php](https://www.php.net/manual/en/context.ssl.php)

```php
public function option(string $key, mixed $value): self;
```

### Socket Server

_**Example :**_

```php
require_once "../vendor/autoload.php";
use \InitPHP\Socket\Socket;
use \InitPHP\Socket\Interfaces\SocketServerInterface;

$server = Socket::server(Socket::TLS, '127.0.0.1', 8080);
$server->connection();

$server->live(function (SocketServerInterface $socket) {
    switch ($socket->read()) {
        case 'exit' : 
            $socket->write('Goodbye!');
            return;
        case 'write' :
            $socket->write('Run write command.');
        break;
        case 'read' :
            $socket->write('Run read command.');
        break;
        default: return;
    }
});
```

### Socket Client

_**Example :**_

```php
require_once "../vendor/autoload.php";
use \InitPHP\Socket\Socket;

$client = Socket::client(Socket::SSL, 'smtp.gmail.com', 465);

$client->option('verify_peer', false)
    ->option('verify_peer_name', false);

$client->connection();

$client->write('EHLO [127.0.0.1]');

echo $client->read();
```

_In the above example, a simple smtp connection to gmail is made._

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright © 2022 [MIT License](./LICENSE)
