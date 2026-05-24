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
use function socket_read;
use function socket_strerror;
use function socket_write;

use const PHP_BINARY_READ;
use const SOCK_STREAM;

final class TCP extends AbstractClient
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
        $proto = getprotobyname('tcp');
        $socket = @socket_create($this->domain->toAddressFamily(), SOCK_STREAM, $proto === false ? 0 : $proto);
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

    public function read(int $length = 1024, int $type = PHP_BINARY_READ): ?string
    {
        if ($this->socket === null) {
            return null;
        }
        $bytes = @socket_read($this->socket, $length, $type);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return $bytes;
    }

    public function write(string $data): ?int
    {
        if ($this->socket === null) {
            return null;
        }
        $written = @socket_write($this->socket, $data, \strlen($data));

        return $written === false ? null : $written;
    }

    public function getSocket(): ?Socket
    {
        return $this->socket;
    }
}
