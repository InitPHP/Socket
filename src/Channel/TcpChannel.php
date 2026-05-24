<?php

declare(strict_types=1);

namespace InitPHP\Socket\Channel;

use InitPHP\Socket\Interfaces\ChannelInterface;
use Socket;

use function socket_close;
use function socket_last_error;
use function socket_recv;
use function socket_write;

use const MSG_DONTWAIT;
use const MSG_PEEK;
use const PHP_BINARY_READ;
use const SOCKET_EAGAIN;
use const SOCKET_EWOULDBLOCK;

final class TcpChannel implements ChannelInterface
{
    private ?Socket $socket;

    public function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    public function read(int $length = 1024, ?int $flag = null): ?string
    {
        if ($this->socket === null) {
            return null;
        }
        $flag ??= PHP_BINARY_READ;
        $bytes = @socket_read($this->socket, $length, $flag);
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

    public function close(): bool
    {
        if ($this->socket === null) {
            return true;
        }
        @socket_close($this->socket);
        $this->socket = null;

        return true;
    }

    public function isAlive(): bool
    {
        if ($this->socket === null) {
            return false;
        }
        $buffer = '';
        $result = @socket_recv($this->socket, $buffer, 1, MSG_PEEK | MSG_DONTWAIT);
        if ($result === 0) {
            return false;
        }
        if ($result === false) {
            $err = socket_last_error($this->socket);

            return \in_array($err, [SOCKET_EAGAIN, SOCKET_EWOULDBLOCK], true);
        }

        return true;
    }

    public function getResource(): ?Socket
    {
        return $this->socket;
    }
}
