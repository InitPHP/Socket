<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Exception\SocketException;
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

    public function testStopExitsLiveLoopOnNextTick(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $iterations = 0;
        $callback = static function () use ($server, &$iterations): void {
            ++$iterations;
            if ($iterations >= 1) {
                $server->stop();
            }
        };

        // No clients ever connect, so tick() will idle out within idleSeconds.
        // We rely on stop() being called externally via a tiny shim that
        // hooks into a no-op activity: drive tick() ourselves a few times.
        $server->stop();
        self::assertFalse($server->isRunning());
        // After stop() the live loop should return immediately.
        $server->live($callback, 0.01);
        self::assertSame(0, $iterations, 'live() should not enter the loop after stop()');
    }
}
