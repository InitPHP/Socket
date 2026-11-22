# Recipe — Custom Channel

`ChannelInterface` is the strategy seam for transport-specific I/O. Implementing your own opens the package to anything you can read from and write to — an in-process pipe, a Redis pub/sub channel, a fake for tests, a custom binary framing on top of an existing socket.

## The contract

```php
namespace InitPHP\Socket\Interfaces;

interface ChannelInterface
{
    public function read(int $length = 1024, ?int $flag = null): ?string;
    public function write(string $data): ?int;
    public function close(): bool;
    public function isAlive(): bool;
    public function getResource(): mixed;
}
```

Five methods. No setup, no teardown, no lifecycle hooks — `__construct` is yours to shape.

## Example: in-memory channel

A channel backed by two PHP strings. The most useful shape for unit tests: you put bytes into one direction and read them from the other.

```php
namespace App\Net;

use InitPHP\Socket\Interfaces\ChannelInterface;

final class InMemoryChannel implements ChannelInterface
{
    private string $inbound  = '';   // what the channel reads
    private string $outbound = '';   // what the channel writes
    private bool   $alive    = true;

    public function pushInbound(string $data): void { $this->inbound .= $data; }
    public function drainOutbound(): string         { $b = $this->outbound; $this->outbound = ''; return $b; }

    public function read(int $length = 1024, ?int $flag = null): ?string
    {
        if ($this->inbound === '' || $length < 1) {
            return null;
        }
        $chunk = substr($this->inbound, 0, $length);
        $this->inbound = substr($this->inbound, strlen($chunk));
        return $chunk;
    }

    public function write(string $data): ?int
    {
        if (!$this->alive) {
            return null;
        }
        $this->outbound .= $data;
        return strlen($data);
    }

    public function close(): bool       { $this->alive = false; return true; }
    public function isAlive(): bool     { return $this->alive; }
    public function getResource(): mixed { return null; }
}
```

Now wrap it in a `ServerConnection` and use it anywhere a real connection would work:

```php
use InitPHP\Socket\Server\ServerConnection;

$channel = new InMemoryChannel();
$conn    = new ServerConnection($channel);

$channel->pushInbound("hello\n");

echo $conn->read(1024);   // "hello\n"

$conn->write("hi back\n");
echo $channel->drainOutbound();   // "hi back\n"
```

This is exactly how the package's [Testing Strategy](Testing-Strategy.md) exercises broadcast and registry logic without binding any real ports.

## Example: line-framing decorator

A channel that wraps another channel and only returns complete `\n`-terminated lines from `read()`. Everything else passes through.

```php
namespace App\Net;

use InitPHP\Socket\Interfaces\ChannelInterface;

final class LineFramingChannel implements ChannelInterface
{
    private string $rxBuffer = '';

    public function __construct(private readonly ChannelInterface $inner) {}

    public function read(int $length = 1024, ?int $flag = null): ?string
    {
        // Pull as much as possible from the inner channel into the buffer.
        while (true) {
            $more = $this->inner->read($length, $flag);
            if ($more === null) {
                break;
            }
            $this->rxBuffer .= $more;
        }
        $pos = strpos($this->rxBuffer, "\n");
        if ($pos === false) {
            return null;
        }
        $line = substr($this->rxBuffer, 0, $pos + 1);
        $this->rxBuffer = substr($this->rxBuffer, $pos + 1);
        return $line;
    }

    public function write(string $data): ?int       { return $this->inner->write($data); }
    public function close(): bool                    { return $this->inner->close(); }
    public function isAlive(): bool                  { return $this->inner->isAlive(); }
    public function getResource(): mixed             { return $this->inner->getResource(); }
}
```

Use it by wrapping the channel a real `ServerConnection` carries:

```php
$server->live(function ($srv, $conn) {
    $framed = new LineFramingChannel($conn->getChannel());
    while (($line = $framed->read(4096)) !== null) {
        // ... handle one whole line ...
    }
});
```

## What to remember

- **`read()` must not raise.** Return `null` for "nothing right now" or "broken". Raising bubbles up into `live()` / `tick()` and the package only swallows it inside `broadcast()`.
- **`write()` returns bytes written.** A short write (`strlen($data) > $written`) is still a success — the caller decides whether to retry.
- **`close()` is idempotent.** Make double-close safe.
- **`isAlive()` is non-destructive.** It must not consume from the underlying transport; the user-callback expects to read those bytes itself.
- **`getResource()` is informational.** Used by `socket_select` / `stream_select` only when the resource is a `\Socket` / stream resource. If your channel has no native handle, return `null` — but then it cannot participate in a real server's read set without a wrapping mechanism.

## Plugging into a server

There is no `attachChannel()` on the server. The package owns the accept logic and constructs channels itself. Hooks that fit your channel:

- **Subclass `AbstractServer`** and override `tick()` to build your own channels.
- **Wrap an existing connection's channel** post-accept, like `LineFramingChannel` above.
- **Skip the server entirely** and use channels as standalone I/O objects in your own code.

## See also

- **[Architecture](Architecture.md)** — where channels sit in the layering.
- **[Connection and Channel](Connection-and-Channel.md)** — the existing `Tcp` / `Udp` / `Stream` channels.
- **[Testing Strategy](Testing-Strategy.md)** — fake channels in unit tests.
