# Cookbook — chat server

A small TCP chat server that supports three commands:

- `REGISTER <name>` — claim a name for the rest of the session.
- `SEND @<name> <message>` — direct message to a registered peer.
- anything else — broadcast to every connected client.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Interfaces\{SocketServerInterface, SocketConnectionInterface};

$server = Socket::server(Transport::TCP, '127.0.0.1', 8080);
$server->listen();

echo "Chat server listening on 127.0.0.1:8080\n";

$server->live(function (SocketServerInterface $srv, SocketConnectionInterface $conn) {
    $input = $conn->read(4096);
    if ($input === null) {
        return;
    }
    $input = \trim($input);
    if ($input === '') {
        return;
    }

    if (\in_array($input, ['quit', 'exit'], true)) {
        $conn->write("Goodbye!\n");
        $conn->close();
        return;
    }

    if (\preg_match('/^REGISTER\s+([\w-]{3,})$/i', $input, $m) === 1) {
        $srv->register($m[1], $conn);
        $conn->write("Registered as {$m[1]}\n");
        return;
    }

    if (\preg_match('/^SEND\s+@([\w-]+)\s+(.+)$/i', $input, $m) === 1) {
        $srv->broadcast("[{$conn->getId()} → {$m[1]}] {$m[2]}\n", $m[1]);
        return;
    }

    $srv->broadcast("[{$conn->getId()}] {$input}\n");
});
```

Try it from two terminals:

```bash
# Terminal A
$ nc 127.0.0.1 8080
REGISTER alice
Registered as alice

# Terminal B
$ nc 127.0.0.1 8080
REGISTER bob
Registered as bob
SEND @alice hey there
```

## Why it works

- `register(id, conn)` records the mapping so `broadcast(message, id)`
  can find the right channel.
- Until a client `REGISTER`s, `$conn->getId()` is `null` — the broadcast
  line shows `null` which is fine for a demo. In a real product you
  would refuse `SEND` until the sender is named.
- `$conn->close()` immediately tears the channel down; the next
  `tick()` finds the socket dead and evicts it from `getClients()`.
