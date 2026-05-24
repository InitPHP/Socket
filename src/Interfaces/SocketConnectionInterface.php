<?php

declare(strict_types=1);

namespace InitPHP\Socket\Interfaces;

interface SocketConnectionInterface
{
    /**
     * Assign a human-readable identifier to this connection. Used by
     * {@see SocketServerInterface::broadcast()} for targeted delivery.
     */
    public function setId(int|string $id): static;

    public function getId(): int|string|null;

    /**
     * Read up to $length bytes from the peer.
     *
     * Returns the payload, or null when nothing was read (no data
     * available, peer closed or an error occurred).
     */
    public function read(int $length = 1024): ?string;

    /**
     * Send $data to the peer. Returns the number of bytes actually
     * written, or null on failure.
     */
    public function write(string $data): ?int;

    /**
     * Close the connection. Safe to call multiple times.
     */
    public function close(): bool;

    /**
     * Returns true while the connection is still usable. Non-destructive.
     */
    public function isAlive(): bool;

    /**
     * Native handle (\Socket for ext-sockets, stream resource for TLS/SSL).
     */
    public function getSocket(): mixed;

    public function getChannel(): ChannelInterface;
}
