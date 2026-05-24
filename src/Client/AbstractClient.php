<?php

declare(strict_types=1);

namespace InitPHP\Socket\Client;

use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use InitPHP\Socket\Interfaces\SocketClientInterface;

abstract class AbstractClient implements SocketClientInterface
{
    public function __construct(
        protected readonly string $host,
        protected readonly int $port,
    ) {
        if ($host === '') {
            throw new SocketInvalidArgumentException('Client host must not be empty.');
        }
        if ($port <= 0 || $port > 65535) {
            throw new SocketInvalidArgumentException('Client port must be between 1 and 65535.');
        }
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
