<?php

declare(strict_types=1);

namespace InitPHP\Socket\Interfaces;

interface SocketServerInterface
{
    public function getHost(): string;

    public function getPort(): int;

    /**
     * The listening socket. \Socket for ext-sockets servers, stream resource
     * for TLS/SSL servers, or null before {@see self::listen()} succeeds.
     */
    public function getSocket(): mixed;

    /**
     * @return array<int|string, SocketConnectionInterface>
     */
    public function getClients(): array;

    /**
     * Bind to host:port and start listening. Does NOT accept any client —
     * use {@see self::live()} to run the accept/dispatch loop.
     */
    public function listen(): static;

    /**
     * Stop listening and close every active client connection.
     */
    public function close(): bool;

    /**
     * Send $message to one or more clients.
     *
     * - null  → every connected client
     * - int|string → the client previously registered with that id
     * - array → multiple ids
     *
     * Returns true if at least one delivery was attempted, false on
     * unrecoverable errors.
     *
     * @param int|string|array<int, int|string>|null $clients
     */
    public function broadcast(string $message, int|string|array|null $clients = null): bool;

    /**
     * Associate an identifier with a connection so it can be addressed by
     * {@see self::broadcast()}.
     */
    public function register(int|string $id, SocketConnectionInterface $client): bool;

    /**
     * Run the accept/dispatch loop. Blocks until {@see self::stop()} is
     * called or a signal interrupts it.
     *
     * The callback receives the server and the active connection; it is
     * invoked whenever a connection has new inbound data (or, for UDP,
     * whenever a datagram arrives).
     *
     * @param callable(SocketServerInterface, SocketConnectionInterface): void $callback
     */
    public function live(callable $callback, float $idleSeconds = 0.05): void;

    /**
     * Run a single iteration of the accept/dispatch loop. Returns the
     * number of events processed during the tick (0 on idle).
     *
     * Useful for embedding the server inside another event loop or for
     * deterministic testing. The waitSeconds argument is the maximum time
     * the underlying select() may block while waiting for activity.
     *
     * @param callable(SocketServerInterface, SocketConnectionInterface): void $callback
     */
    public function tick(callable $callback, float $waitSeconds = 0.0): int;

    /**
     * Cooperatively stop the loop started by {@see self::live()}.
     */
    public function stop(): void;

    /**
     * Sleep for $seconds (supports sub-second precision).
     */
    public function wait(float $seconds): void;
}
