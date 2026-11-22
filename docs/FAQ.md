# FAQ

A grab-bag of questions that come up often. If something here doesn't match what you see, [open an issue](https://github.com/InitPHP/Socket/issues) — both the docs and the package improve from real reports.

## Is this a framework? An async runtime?

Neither. `initphp/socket` is a thin, transport-agnostic façade over `ext-sockets` and the stream socket family. It runs a non-blocking `select`-driven loop inside your normal PHP process. There is no fiber scheduler, no promise framework, no daemonisation — those layers are someone else's job (or yours).

## Can I run multiple servers in the same process?

Yes — but the package does not multiplex them for you. Each server has its own `tick()` / `live()` loop. Wire them together in your own scheduler:

```php
$tcp->listen();
$udp->listen();
while ($running) {
    $tcp->tick($onTcp, waitSeconds: 0.0);
    $udp->tick($onUdp, waitSeconds: 0.0);
    usleep(1_000);
}
```

See [Event Loop Integration](Event-Loop-Integration.md) for patterns.

## Why does `tick()` exist on top of `live()`?

`live()` is "easy mode" — start it and don't worry about anything else. `tick()` is "embedding mode" — let your existing event loop decide when the socket layer gets CPU. They share the same loop body; `live()` just calls `tick()` until something flips `$running` to false.

Tests use `tick()` because it gives deterministic single-step control.

## How do I gracefully shut down a `live()` loop?

Call `$server->stop()` from inside the callback, or from a signal handler:

```php
pcntl_signal(SIGTERM, fn () => $server->stop());
pcntl_signal(SIGINT,  fn () => $server->stop());
pcntl_async_signals(true);

$server->live(...);
$server->close();
```

`stop()` only flips the flag; you still call `close()` afterwards to tear down sockets.

## Why does my server only accept one client?

You almost certainly hit the 1.x bug. 2.x's `live()` accepts many clients in a non-blocking loop. If you upgraded, double-check you replaced `connection()` with `listen()` and that you're not calling `socket_accept` yourself — see the [Migration Guide](Migration-Guide.md).

If you're already on 2.x and seeing one-accept-only behaviour, please [file an issue](https://github.com/InitPHP/Socket/issues).

## Why does `$conn->read()` return `null`?

Three possibilities:

1. Nothing to read right now (the socket is non-blocking and the kernel has no bytes for you).
2. The peer closed the connection (EOF).
3. An underlying error.

The package returns `null` for all three because most callers want a single "no data" branch. If you need to distinguish them, inspect `$conn->isAlive()` and `socket_last_error()` / `socket_strerror()`.

## Why does my callback receive an empty string?

It does **not**, by contract: `read()` returns `?string` and `null` covers both "no data" and "empty string", so the callback never sees an empty string from `$conn->read()`. If you see `""` somewhere, the source is your own code or a wrapper around the connection. (Channels are also expected to honour this contract.)

## Why does `live()` use CPU when nothing is happening?

It calls `socket_select` with `idleSeconds` as the timeout. The default is 50 ms — so the loop wakes up 20 times a second even when idle. If that is too aggressive, raise `idleSeconds`:

```php
$server->live($callback, idleSeconds: 1.0);   // wake once a second when idle
```

Trade-off: a higher idle means new accept events are detected later (latency-bound by the timeout). For most chat-style servers, `0.05` is fine. For mostly-idle services, `1.0` is fine too.

## What about Windows?

The package itself runs on Windows (the `ext-sockets` and stream wrappers exist there). The **TLS integration test** uses `pcntl_fork` and is automatically skipped on Windows. The actual TLS server / client classes work the same on every OS.

## What about backpressure on `write()`?

`write()` returns the number of bytes the OS actually accepted. A "short write" — `strlen($data) > $written` — means the OS buffer is partly full. The package does **not** loop or queue the unsent bytes for you; you decide whether to retry or back off. In 99% of LAN / loopback scenarios, single-shot writes work, but plan for short writes in real-world deployments.

## How do I detect peer disconnect without reading?

`$conn->isAlive()`. For TCP it uses `MSG_PEEK | MSG_DONTWAIT`, for streams it uses `feof()`. Both are non-destructive. The `live()` / `tick()` loop already calls `isAlive()` between accept and dispatch, so a closed peer is evicted before your callback runs.

## Why is my TLS handshake timing out?

Three common reasons:

1. **The listening stream was set non-blocking** (1.x style). In 2.x the package keeps the listen socket blocking specifically so `stream_socket_accept` has time to finish the handshake.
2. **The `timeout` constructor argument is too small.** The handshake takes one to a few hundred milliseconds; budget at least 1.0 second.
3. **A certificate / hostname mismatch.** Set `verify_peer = false` temporarily to confirm; if it works, fix the trust configuration. See [SSL Context Options](SSL-Context-Options.md).

## Why does my UDP server keep clients that never spoke again?

UDP has no kernel-level disconnect signal. The package keeps the channel until you `close()` it explicitly. Track `lastSeen` per peer and evict on TTL — example in [Transport UDP](Transport-UDP.md).

## Can I use this with ReactPHP / Amp / Swoole?

Yes — drive `tick()` from their loop. `tick($cb, waitSeconds: 0.0)` is non-blocking and returns immediately; you yield back to the host loop and pick up next iteration. The package does not integrate with any of these natively, but the seam is intentional. See [Event Loop Integration](Event-Loop-Integration.md).

## How do I add a new transport (e.g. WebSocket)?

Implement `ChannelInterface` for the I/O and (optionally) extend `AbstractServer` for the accept logic. The factory is closed for extension by design — call your concrete server directly. See [Recipe Custom Channel](Recipe-Custom-Channel.md).

## How is the test suite so fast?

Most of it is unit tests over fakes (no I/O). The integration tests run on loopback with `tick()` to avoid sleeping. The TLS test is the only one that forks, and it forks only the client side. Full suite: **96 tests in under a second**.

## Where do I report a bug?

[InitPHP/Socket/issues](https://github.com/InitPHP/Socket/issues). Include:

- Package version (`composer show initphp/socket`).
- PHP version and OS.
- A minimal reproducer (≤ 30 lines).
- Expected vs actual output.

A failing test or a `nc` transcript helps a lot.

## See also

- **[Migration Guide](Migration-Guide.md)** — moving from 1.x.
- **[Architecture](Architecture.md)** — the underlying model.
- **[Testing Strategy](Testing-Strategy.md)** — write deterministic tests for your own server code.
