<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\TCP as TcpClient;
use InitPHP\Socket\Server\TCP as TcpServer;

final class TcpDisconnectTest extends IntegrationTestCase
{
    public function testServerEvictsClientAfterPeerDisconnects(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new TcpClient('127.0.0.1', $port);
        $client->connect();

        // Accept the client.
        $server->tick(static fn () => null, 0.2);
        self::assertCount(1, $server->getClients());

        // Client drops the connection.
        $client->disconnect();

        // The next tick should detect EOF on the dead socket and evict it.
        // We may need a couple of iterations for the kernel to surface the close.
        $eviction = false;
        for ($i = 0; $i < 20 && !$eviction; ++$i) {
            $server->tick(static fn () => null, 0.05);
            if (\count($server->getClients()) === 0) {
                $eviction = true;
            }
        }
        self::assertTrue($eviction, 'server did not evict the disconnected client');
    }

    public function testBroadcastSkipsDeadClient(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $alive = new TcpClient('127.0.0.1', $port);
        $alive->connect();
        $this->registerCleanup($alive->disconnect(...));

        $deadOnArrival = new TcpClient('127.0.0.1', $port);
        $deadOnArrival->connect();
        $deadOnArrival->disconnect();

        // Accept both.
        $server->tick(static fn () => null, 0.2);
        $server->tick(static fn () => null, 0.2);
        self::assertCount(2, $server->getClients());

        // Broadcast — the dead client write call should silently no-op and
        // not raise; the alive client should still receive the message.
        self::assertTrue($server->broadcast('beacon'));

        $reply = null;
        for ($i = 0; $i < 30 && $reply === null; ++$i) {
            $reply = $alive->read(1024);
            if ($reply === null) {
                usleep(10_000);
            }
        }
        self::assertSame('beacon', $reply);
    }
}
