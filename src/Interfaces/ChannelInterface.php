<?php

declare(strict_types=1);

namespace InitPHP\Socket\Interfaces;

interface ChannelInterface
{
    /**
     * Read up to $length bytes from the channel.
     *
     * Returns the read payload, or null when the underlying transport
     * had no data available, was closed, or signalled an error. Channel
     * implementations MUST NOT return null when bytes were actually read.
     */
    public function read(int $length = 1024, ?int $flag = null): ?string;

    /**
     * Write $data to the channel.
     *
     * Returns the number of bytes actually written, or null on failure.
     */
    public function write(string $data): ?int;

    /**
     * Close the underlying transport.
     *
     * Channels are single-use — once closed, all further operations
     * return null/false/zero as appropriate. Closing an already-closed
     * channel is a no-op and returns true.
     */
    public function close(): bool;

    /**
     * Returns true while the channel is still usable.
     *
     * Implementations MUST NOT consume data from the wire while checking
     * liveness — use peek semantics or peer-state hints only.
     */
    public function isAlive(): bool;

    /**
     * The native handle backing this channel.
     *
     * - {@see \Socket} for ext-sockets backed channels (TCP, UDP).
     * - A stream resource (`resource` of type "stream") for SSL/TLS.
     * - null after the channel has been closed.
     */
    public function getResource(): mixed;
}
