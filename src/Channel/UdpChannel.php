<?php

declare(strict_types=1);

namespace InitPHP\Socket\Channel;

use InitPHP\Socket\Interfaces\ChannelInterface;
use Socket;

use function socket_sendto;
use function substr;

/**
 * A UDP channel binds a peer (host:port) to a server's listening socket.
 *
 * UDP is connectionless, so a single listening socket services every peer.
 * The server is responsible for routing inbound datagrams into the right
 * channel via {@see self::feed()}. Reads drain the channel's local buffer
 * rather than touching the wire directly. Writes send to the bound peer.
 */
final class UdpChannel implements ChannelInterface
{
    private ?Socket $socket;

    private string $buffer = '';

    private bool $alive = true;

    public function __construct(
        Socket $listeningSocket,
        private readonly string $peerHost,
        private readonly int $peerPort,
    ) {
        $this->socket = $listeningSocket;
    }

    /**
     * Append data routed by the UDP server for this peer.
     */
    public function feed(string $data): void
    {
        $this->buffer .= $data;
    }

    public function read(int $length = 1024, ?int $flag = null): ?string
    {
        if ($this->buffer === '') {
            return null;
        }
        $chunk = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, \strlen($chunk));

        return $chunk;
    }

    public function write(string $data): ?int
    {
        if ($this->socket === null) {
            return null;
        }
        $sent = @socket_sendto($this->socket, $data, \strlen($data), 0, $this->peerHost, $this->peerPort);

        return $sent === false ? null : $sent;
    }

    public function close(): bool
    {
        $this->socket = null;
        $this->alive = false;
        $this->buffer = '';

        return true;
    }

    public function isAlive(): bool
    {
        return $this->alive && $this->socket !== null;
    }

    public function getResource(): ?Socket
    {
        return $this->socket;
    }

    public function getPeerHost(): string
    {
        return $this->peerHost;
    }

    public function getPeerPort(): int
    {
        return $this->peerPort;
    }

    public function peerKey(): string
    {
        return $this->peerHost . ':' . $this->peerPort;
    }
}
