# Recipe — Broadcast

`broadcast()` is the package's fan-out primitive. The same method covers three patterns; the right one is selected by the type of the second argument.

```php
public function broadcast(
    string $message,
    int|string|array|null $clients = null,
): bool;
```

| `$clients` | Reach |
|---|---|
| `null` | Every alive client. |
| `int|string` | The one client previously `register()`-ed with this id. Silently no-ops if no such id is mapped. |
| `array<int|string>` | The clients matching any of the supplied ids. Unknown ids are skipped. |

Return value: `true` if the dispatch completed (even when zero clients matched). `false` only if something in the loop raised — which the package internally swallows to keep `live()` resilient.

## All connected clients

```php
$server->live(function ($srv, $conn) {
    $payload = $conn->read();
    if ($payload !== null) {
        $srv->broadcast($payload);   // everyone sees the bytes the sender posted
    }
});
```

`isAlive()` is checked per recipient before `write()`. Dead connections in the registry are skipped silently and evicted on the next loop iteration.

## A single named client

```php
$server->live(function ($srv, $conn) {
    $line = trim((string) $conn->read());
    if (preg_match('/^TO\s+(\S+)\s+(.+)$/', $line, $m)) {
        $srv->broadcast($m[2], $m[1]);  // single id
    }
});
```

If `$m[1]` is not mapped (nobody registered with that name), the call is a no-op.

## A list of named clients

```php
$srv->broadcast("⚠ maintenance window starts in 10 min", ['admin', 'oncall', 'sre']);
```

The package iterates the list, looks each id up in `clientIdMap`, and writes to each match. Unknown ids cost nothing.

## Iterating manually

For anything not covered by the three modes — sending to clients filtered by an arbitrary predicate, for example — use `getClients()` directly:

```php
$server->live(function ($srv, $conn) {
    if ($conn->read() !== 'hello vips') {
        return;
    }
    foreach ($srv->getClients() as $id => $other) {
        if (is_string($id) && str_starts_with($id, 'vip-')) {
            $other->write("hi VIP {$id}\n");
        }
    }
});
```

`getClients()` returns a map keyed by registered id (string) **or** the server's internal numeric key for clients that never registered. See [Connection and Channel](Connection-and-Channel.md) for the identity contract.

## UDP broadcasting

UDP has the same `broadcast()` shape but with caveats:

- A peer is "known" only after speaking once — there is no discovery.
- Each `write()` is a separate `socket_sendto`; the kernel may drop any one of them.
- `isAlive()` on a UDP connection is application-level — the server keeps writing to peers it has not heard from until you `close()` them.

```php
$server = Socket::server(Transport::UDP, '0.0.0.0', 9000);
$server->listen();

$server->live(function ($srv, $conn) {
    $payload = $conn->read(65535);
    if ($payload === 'ANNOUNCE') {
        $srv->broadcast('hello room', null);
    }
});
```

## Order, atomicity, error handling

- **Order** — clients receive messages in the order they were registered. There is no priority queue.
- **Atomicity** — broadcast is per-recipient `write()`. If the OS buffers fill mid-loop, that recipient's write may be short or fail; others continue.
- **Error handling** — `broadcast()` catches `Throwable` and returns `false`. The wrap exists so a misbehaving channel cannot kill the loop. Inspect the return value when you care.

## See also

- **[Recipe Chat Server](Recipe-Chat-Server.md)** — broadcast in a richer protocol context.
- **[Server Lifecycle](Server-Lifecycle.md)** — when `broadcast()` is a no-op (before `listen()`, after `close()`).
- **[Connection and Channel](Connection-and-Channel.md)** — identity and the `clientIdMap`.
