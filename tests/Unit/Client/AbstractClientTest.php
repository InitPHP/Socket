<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Client;

use InitPHP\Socket\Client\TCP as TcpClient;
use InitPHP\Socket\Client\UDP as UdpClient;
use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AbstractClientTest extends TestCase
{
    public function testHostAndPortGetters(): void
    {
        $client = new TcpClient('10.0.0.1', 4242);
        self::assertSame('10.0.0.1', $client->getHost());
        self::assertSame(4242, $client->getPort());
    }

    public function testEmptyHostIsRejected(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        new TcpClient('', 80);
    }

    public function testZeroPortIsRejected(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        new UdpClient('127.0.0.1', 0);
    }

    public function testOutOfRangePortIsRejected(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        new UdpClient('127.0.0.1', 100000);
    }

    public function testGetSocketReturnsNullBeforeConnect(): void
    {
        $client = new TcpClient('127.0.0.1', 9999);
        self::assertNull($client->getSocket());
    }

    public function testDisconnectIsIdempotentBeforeConnect(): void
    {
        $client = new TcpClient('127.0.0.1', 9999);
        self::assertTrue($client->disconnect());
        self::assertTrue($client->disconnect());
    }

    public function testReadAndWriteReturnNullBeforeConnect(): void
    {
        $client = new TcpClient('127.0.0.1', 9999);
        self::assertNull($client->read(64));
        self::assertNull($client->write('x'));
    }

    public function testUdpReadAndWriteReturnNullBeforeConnect(): void
    {
        $client = new UdpClient('127.0.0.1', 9999);
        self::assertNull($client->read(64));
        self::assertNull($client->write('x'));
        self::assertNull($client->getSocket());
        self::assertTrue($client->disconnect());
    }

    public function testUdpReadRejectsZeroLength(): void
    {
        $client = new UdpClient('127.0.0.1', 9999);
        self::assertNull($client->read(0));
    }
}
