<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Server;

use InitPHP\Socket\Exception\SocketException;
use InitPHP\Socket\Server\UDP as UdpServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UdpServer::class)]
final class UdpServerOptionsTest extends TestCase
{
    public function testCloseIsIdempotentBeforeListen(): void
    {
        $server = new UdpServer('127.0.0.1', 9000);
        self::assertTrue($server->close());
        self::assertTrue($server->close());
        self::assertNull($server->getSocket());
        self::assertFalse($server->isRunning());
    }

    public function testTickBeforeListenThrows(): void
    {
        $server = new UdpServer('127.0.0.1', 9000);
        $this->expectException(SocketException::class);
        $server->tick(static fn () => null, 0.0);
    }

    public function testGetHostAndPortReturnConfiguredValues(): void
    {
        $server = new UdpServer('192.168.0.5', 5300);
        self::assertSame('192.168.0.5', $server->getHost());
        self::assertSame(5300, $server->getPort());
    }
}
