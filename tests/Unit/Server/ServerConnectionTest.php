<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Server;

use InitPHP\Socket\Interfaces\ChannelInterface;
use InitPHP\Socket\Server\ServerConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerConnection::class)]
final class ServerConnectionTest extends TestCase
{
    public function testDelegatesReadWriteCloseToChannel(): void
    {
        $channel = new FakeChannel();
        $channel->readReturn = 'hello';
        $channel->writeReturn = 5;

        $connection = new ServerConnection($channel);

        self::assertSame('hello', $connection->read(128));
        self::assertSame([128, null], $channel->readCalls[0]);

        self::assertSame(5, $connection->write('hello'));
        self::assertSame(['hello'], $channel->writeCalls[0]);

        self::assertTrue($connection->close());
        self::assertTrue($channel->closed);
    }

    public function testIdGetterAndSetter(): void
    {
        $connection = new ServerConnection(new FakeChannel());
        self::assertNull($connection->getId());
        $connection->setId('admin');
        self::assertSame('admin', $connection->getId());
        $connection->setId(42);
        self::assertSame(42, $connection->getId());
    }

    public function testExposesChannelAndUnderlyingResource(): void
    {
        $channel = new FakeChannel();
        $resource = (object) ['marker' => true];
        $channel->resource = $resource;

        $connection = new ServerConnection($channel);

        self::assertSame($channel, $connection->getChannel());
        self::assertSame($resource, $connection->getSocket());
    }
}

final class FakeChannel implements ChannelInterface
{
    public mixed $resource = null;

    public ?string $readReturn = null;

    public ?int $writeReturn = null;

    public bool $alive = true;

    public bool $closed = false;

    /** @var array<int, array{0: int, 1: ?int}> */
    public array $readCalls = [];

    /** @var array<int, array{0: string}> */
    public array $writeCalls = [];

    public function read(int $length = 1024, ?int $flag = null): ?string
    {
        $this->readCalls[] = [$length, $flag];

        return $this->readReturn;
    }

    public function write(string $data): ?int
    {
        $this->writeCalls[] = [$data];

        return $this->writeReturn;
    }

    public function close(): bool
    {
        $this->closed = true;

        return true;
    }

    public function isAlive(): bool
    {
        return $this->alive && !$this->closed;
    }

    public function getResource(): mixed
    {
        return $this->resource;
    }
}
