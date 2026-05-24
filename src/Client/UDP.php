<?php

declare(strict_types=1);

namespace InitPHP\Socket\Client;

use InitPHP\Socket\Enum\Domain;
use InitPHP\Socket\Exception\SocketConnectionException;
use InitPHP\Socket\Exception\SocketException;
use Socket;

use function getprotobyname;
use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_last_error;
use function socket_recv;
use function socket_send;
use function socket_strerror;

use const SOCK_DGRAM;

final class UDP extends AbstractClient
{
    private ?Socket $socket = null;

    public function __construct(
        string $host,
        int $port,
        private readonly Domain $domain = Domain::V4,
    ) {
        parent::__construct($host, $port);
    }

    public function connect(): static
    {
        if ($this->socket !== null) {
            throw new SocketException('Client is already connected.');
        }
        $proto = getprotobyname('udp');
        $socket = @socket_create($this->domain->toAddressFamily(), SOCK_DGRAM, $proto === false ? 0 : $proto);
        if (!$socket instanceof Socket) {
            throw new SocketException('socket_create failed: ' . socket_strerror(socket_last_error()));
        }
        if (@socket_connect($socket, $this->host, $this->port) === false) {
            $err = socket_strerror(socket_last_error($socket));
            socket_close($socket);
            throw new SocketConnectionException('socket_connect failed: ' . $err);
        }
        $this->socket = $socket;

        return $this;
    }

    public function disconnect(): bool
    {
        if ($this->socket === null) {
            return true;
        }
        @socket_close($this->socket);
        $this->socket = null;

        return true;
    }

    /**
     * @param int $flags Bitmask of MSG_OOB, MSG_PEEK, MSG_WAITALL, MSG_DONTWAIT
     */
    public function read(int $length = 1024, int $flags = 0): ?string
    {
        if ($this->socket === null || $length < 1) {
            return null;
        }
        $buf = '';
        $bytes = @socket_recv($this->socket, $buf, $length, $flags);
        if ($bytes === false || $bytes === 0) {
            return null;
        }

        return $buf;
    }

    /**
     * @param int $flags Bitmask of MSG_OOB, MSG_EOR, MSG_EOF, MSG_DONTROUTE
     */
    public function write(string $data, int $flags = 0): ?int
    {
        if ($this->socket === null) {
            return null;
        }
        $sent = @socket_send($this->socket, $data, \strlen($data), $flags);

        return $sent === false ? null : $sent;
    }

    public function getSocket(): ?Socket
    {
        return $this->socket;
    }
}
