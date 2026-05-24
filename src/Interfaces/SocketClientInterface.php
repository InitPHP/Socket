<?php

declare(strict_types=1);

namespace InitPHP\Socket\Interfaces;

interface SocketClientInterface
{
    public function getHost(): string;

    public function getPort(): int;

    /**
     * Native socket handle (\Socket for ext-sockets, stream resource for TLS/SSL).
     */
    public function getSocket(): mixed;

    /**
     * Establish the connection to the remote endpoint.
     */
    public function connect(): static;

    /**
     * Close the connection. Returns true on success, false on failure.
     * Calling this on a non-connected client is a no-op and returns true.
     */
    public function disconnect(): bool;

    /**
     * Read up to $length bytes from the remote endpoint.
     *
     * Returns the payload, or null when nothing was read.
     */
    public function read(int $length = 1024): ?string;

    /**
     * Write $data to the remote endpoint.
     *
     * Returns the number of bytes actually written, or null on failure.
     */
    public function write(string $data): ?int;
}
