<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Server;

use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use InitPHP\Socket\Interfaces\ChannelInterface;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;
use InitPHP\Socket\Server\AbstractServer;
use InitPHP\Socket\Server\ServerConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractServer::class)]
final class AbstractServerBroadcastTest extends TestCase
{
    public function testBroadcastWithoutIdsReachesEveryAliveClient(): void
    {
        $server = new TestableServer('127.0.0.1', 9000);
        $alice = $server->attach($this->makeConnection($aliceChannel));
        $bob = $server->attach($this->makeConnection($bobChannel));

        self::assertTrue($server->broadcast('hello'));

        self::assertSame(['hello'], $this->writesOf($aliceChannel));
        self::assertSame(['hello'], $this->writesOf($bobChannel));
    }

    public function testBroadcastSkipsDeadClients(): void
    {
        $server = new TestableServer('127.0.0.1', 9000);
        $server->attach($this->makeConnection($aliveChannel));
        $deadConnection = $this->makeConnection($deadChannel);
        $server->attach($deadConnection);
        $deadChannel->alive = false;

        self::assertTrue($server->broadcast('ping'));

        self::assertSame(['ping'], $this->writesOf($aliveChannel));
        self::assertSame([], $this->writesOf($deadChannel));
    }

    public function testRegisterAllowsTargetedBroadcast(): void
    {
        $server = new TestableServer('127.0.0.1', 9000);
        $adminConnection = $this->makeConnection($adminChannel);
        $guestConnection = $this->makeConnection($guestChannel);
        $server->attach($adminConnection);
        $server->attach($guestConnection);

        self::assertTrue($server->register('admin', $adminConnection));
        self::assertTrue($server->register('guest', $guestConnection));

        $server->broadcast('only-admin', 'admin');
        $server->broadcast('mass-by-list', ['admin', 'guest']);
        $server->broadcast('unknown-noop', 'ghost');

        self::assertSame(['only-admin', 'mass-by-list'], $this->writesOf($adminChannel));
        self::assertSame(['mass-by-list'], $this->writesOf($guestChannel));
        self::assertSame('admin', $adminConnection->getId());
    }

    public function testRegisterReturnsFalseForUnknownConnection(): void
    {
        $server = new TestableServer('127.0.0.1', 9000);
        $foreign = $this->makeConnection($_unused);
        self::assertFalse($server->register('x', $foreign));
    }

    public function testWaitRejectsNegative(): void
    {
        $server = new TestableServer('127.0.0.1', 9000);
        $this->expectException(SocketInvalidArgumentException::class);
        $server->wait(-1.0);
    }

    public function testGetClientsKeyedByIdWhenRegistered(): void
    {
        $server = new TestableServer('127.0.0.1', 9000);
        $a = $this->makeConnection($_a);
        $b = $this->makeConnection($_b);
        $server->attach($a);
        $server->attach($b);
        $server->register('alice', $a);

        $clients = $server->getClients();
        self::assertArrayHasKey('alice', $clients);
        self::assertSame($a, $clients['alice']);
        // unregistered second client falls back to its internal numeric key
        self::assertCount(2, $clients);
    }

    /**
     * @param-out FakeChannel $channel
     */
    private function makeConnection(?ChannelInterface &$channel): SocketConnectionInterface
    {
        $channel = new FakeChannel();

        return new ServerConnection($channel);
    }

    /**
     * @return array<int, string>
     */
    private function writesOf(ChannelInterface $channel): array
    {
        \assert($channel instanceof FakeChannel);

        return array_map(static fn (array $call): string => $call[0], $channel->writeCalls);
    }
}

/**
 * Concrete subclass that exposes the protected addClient() so we can
 * exercise broadcast/register/getClients without touching real sockets.
 */
final class TestableServer extends AbstractServer
{
    public function attach(SocketConnectionInterface $client): int
    {
        return $this->addClient($client);
    }

    public function listen(): static
    {
        return $this;
    }

    public function close(): bool
    {
        return true;
    }

    public function tick(callable $callback, float $waitSeconds = 0.0): int
    {
        return 0;
    }

    public function getSocket(): mixed
    {
        return null;
    }
}
