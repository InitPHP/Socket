<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Server;

use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use InitPHP\Socket\Server\TCP as TcpServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TcpServer::class)]
final class TcpServerOptionsTest extends TestCase
{
    public function testBacklogIsChainable(): void
    {
        $server = new TcpServer('127.0.0.1', 9000);
        self::assertSame($server, $server->backlog(16));
    }

    public function testBacklogRejectsZero(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        (new TcpServer('127.0.0.1', 9000))->backlog(0);
    }

    public function testBacklogRejectsNegative(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        (new TcpServer('127.0.0.1', 9000))->backlog(-1);
    }

    public function testCloseIsIdempotentWhenNeverListened(): void
    {
        $server = new TcpServer('127.0.0.1', 9000);
        self::assertTrue($server->close());
        self::assertTrue($server->close());
        self::assertNull($server->getSocket());
        self::assertFalse($server->isRunning());
    }
}
