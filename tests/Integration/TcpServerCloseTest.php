<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\TCP as TcpClient;
use InitPHP\Socket\Server\TCP as TcpServer;

final class TcpServerCloseTest extends IntegrationTestCase
{
    public function testCloseTearsDownEveryActiveClient(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();

        $clients = [];
        for ($i = 0; $i < 3; ++$i) {
            $c = new TcpClient('127.0.0.1', $port);
            $c->connect();
            $clients[] = $c;
            $this->registerCleanup($c->disconnect(...));
        }
        // Accept all of them.
        for ($i = 0; $i < 3; ++$i) {
            $server->tick(static fn () => null, 0.1);
        }
        self::assertCount(3, $server->getClients());

        self::assertTrue($server->close());
        self::assertSame([], $server->getClients());
        self::assertNull($server->getSocket());
    }

    public function testCustomBacklogIsApplied(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        self::assertSame($server, $server->backlog(2));
        $server->listen();
        $this->registerCleanup($server->close(...));

        // We don't introspect the OS backlog directly; we just exercise the
        // chained setter path and confirm listen() still works afterwards.
        self::assertNotNull($server->getSocket());
    }
}
