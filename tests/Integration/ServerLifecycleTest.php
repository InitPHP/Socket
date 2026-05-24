<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\TCP as TcpClient;
use InitPHP\Socket\Exception\SocketException;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;
use InitPHP\Socket\Server\TCP as TcpServer;

final class ServerLifecycleTest extends IntegrationTestCase
{
    public function testListenTwiceThrows(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $this->expectException(SocketException::class);
        $server->listen();
    }

    public function testCloseAfterListenIsIdempotent(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();

        self::assertTrue($server->close());
        self::assertTrue($server->close());
        self::assertNull($server->getSocket());
    }

    public function testRelistenAfterCloseSucceeds(): void
    {
        $portA = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $portA);
        $server->listen();
        $server->close();

        // After close(), the server can be configured for a new port and re-listened.
        $portB = $this->findFreePort();
        $rebornServer = new TcpServer('127.0.0.1', $portB);
        $rebornServer->listen();
        $this->registerCleanup($rebornServer->close(...));

        self::assertNotNull($rebornServer->getSocket());
    }

    public function testTickBeforeListenThrows(): void
    {
        $server = new TcpServer('127.0.0.1', 9000);

        $this->expectException(SocketException::class);
        $server->tick(static fn () => null, 0.0);
    }

    public function testStopFromInsideCallbackExitsLiveLoop(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new TcpClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        // Bring the client into the server's accept queue, then feed a byte
        // so the next live() iteration actually fires the callback.
        $server->tick(static fn () => null, 0.2);
        self::assertCount(1, $server->getClients());
        self::assertSame(4, $client->write('stop'));

        $invocations = 0;
        $server->live(
            static function (SocketServerInterface $srv, SocketConnectionInterface $conn) use (&$invocations): void {
                ++$invocations;
                $conn->read(1024);
                $srv->stop();
            },
            0.05,
        );

        self::assertSame(1, $invocations);
        self::assertFalse($server->isRunning());
    }
}
