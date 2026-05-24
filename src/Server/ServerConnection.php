<?php

declare(strict_types=1);

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Interfaces\ChannelInterface;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;

final class ServerConnection implements SocketConnectionInterface
{
    private int|string|null $id = null;

    public function __construct(private readonly ChannelInterface $channel)
    {
    }

    public function setId(int|string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function read(int $length = 1024): ?string
    {
        return $this->channel->read($length);
    }

    public function write(string $data): ?int
    {
        return $this->channel->write($data);
    }

    public function close(): bool
    {
        return $this->channel->close();
    }

    public function isAlive(): bool
    {
        return $this->channel->isAlive();
    }

    public function getSocket(): mixed
    {
        return $this->channel->getResource();
    }

    public function getChannel(): ChannelInterface
    {
        return $this->channel;
    }
}
