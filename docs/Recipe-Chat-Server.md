# Recipe — Chat Server

A TCP server that supports named clients and directed messages. The protocol is line-oriented:

| Command | Effect |
|---|---|
| `REGISTER alice` | Claim the name `alice` for the rest of the session. |
| `SEND @bob hello bob` | Direct-message `bob`. |
| `quit` / `exit` | Disconnect. |
| anything else | Broadcast to every connected client. |

## Server

`chat-server.php`:

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
    $line = $conn->read(4096);
    if ($line === null) {
        return;
    }
    $line = trim($line);
    if ($line === '') {
        return;
    }

    if (in_array($line, ['quit', 'exit'], true)) {
        $conn->write("Goodbye!\n");
        $conn->close();
        return;
    }

    if (preg_match('/^REGISTER\s+([\w-]{3,})$/i', $line, $m) === 1) {
        $srv->register($m[1], $conn);
        $conn->write("Registered as {$m[1]}\n");
        return;
    }

    if (preg_match('/^SEND\s+@([\w-]+)\s+(.+)$/i', $line, $m) === 1) {
        $sender = $conn->getId() ?? 'guest';
        $srv->broadcast("[{$sender} → {$m[1]}] {$m[2]}\n", $m[1]);
        return;
    }

    $sender = $conn->getId() ?? 'guest';
    $srv->broadcast("[{$sender}] {$line}\n");
});
```

## Try it

Run the server:

```bash
php chat-server.php
```

Open two more terminals and connect with `nc`:

```text
$ nc 127.0.0.1 8080                              # terminal A
REGISTER alice
Registered as alice

$ nc 127.0.0.1 8080                              # terminal B
REGISTER bob
Registered as bob
SEND @alice hey there
                                                  # terminal A sees:
                                                  # [bob → alice] hey there

hello room                                        # terminal B
                                                  # both terminals see:
                                                  # [bob] hello room
```

## How it works

### Registering an id

`SocketServerInterface::register(int|string, SocketConnectionInterface)` does two things:

1. Calls `setId()` on the connection so future code can identify it.
2. Updates the server's internal `clientIdMap` so `broadcast()` can address the connection by id.

Until `register()` is called, `$conn->getId()` returns `null` and `broadcast(msg, 'alice')` will skip the connection.

### Broadcasting

`broadcast()` accepts three shapes:

```php
$srv->broadcast('mass');                  // every alive client
$srv->broadcast('alice-only', 'alice');   // single id
$srv->broadcast('vips', ['alice','bob']); // multiple ids
```

Unknown ids are silently skipped — the loop short-circuits with a `isset($this->clientIdMap[$id])` check.

### `close()` from inside the callback

`$conn->close()` is safe to call from inside the callback. The server's loop checks `isAlive()` on every iteration before invoking the callback, so the closed connection is evicted on the next `tick()` and never receives a message again.

### Race-condition note

The "register" workflow is application-level — the server does not enforce uniqueness, nor does it verify the registering client is the rightful owner of the name. Add your own auth on top if that matters.

## Variations

### Refuse unregistered users from broadcasting

```php
if ($conn->getId() === null) {
    $conn->write("REGISTER first.\n");
    return;
}
```

Slot that at the top of the broadcast branches.

### Per-room dispatch

`register()` accepts both `int` and `string`. Use composite ids like `"room1#alice"` and target a room with a list:

```php
$srv->broadcast('hi room', array_filter(
    array_map(
        fn ($c) => $c->getId(),
        $srv->getClients(),
    ),
    fn ($id) => is_string($id) && str_starts_with($id, 'room1#'),
));
```

### Drop dead peers proactively

The server already evicts disconnected clients on the next loop iteration. To do it more eagerly (e.g. when running this on UDP where there is no kernel close signal), call `$conn->isAlive()` in your callback and `$conn->close()` if you decide the peer is gone.

## See also

- **[Recipe Broadcast](Recipe-Broadcast.md)** — the dispatch patterns isolated.
- **[Connection and Channel](Connection-and-Channel.md)** — what `setId()` / `getId()` actually do.
- **[Server Lifecycle](Server-Lifecycle.md)** — `register()` / `broadcast()` semantics across states.
