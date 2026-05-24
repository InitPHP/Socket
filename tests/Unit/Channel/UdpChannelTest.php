<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Channel;

use InitPHP\Socket\Channel\UdpChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Socket;

#[CoversClass(UdpChannel::class)]
final class UdpChannelTest extends TestCase
{
    private Socket $socket;

    protected function setUp(): void
    {
        $sock = socket_create(\AF_INET, \SOCK_DGRAM, \SOL_UDP);
        self::assertInstanceOf(Socket::class, $sock);
        $this->socket = $sock;
    }

    protected function tearDown(): void
    {
        @socket_close($this->socket);
    }

    public function testReadDrainsBufferIncrementally(): void
    {
        $channel = new UdpChannel($this->socket, '127.0.0.1', 9999);
        $channel->feed('hello world');
        self::assertSame('hello', $channel->read(5));
        self::assertSame(' world', $channel->read(1024));
        self::assertNull($channel->read(1024));
    }

    public function testReadReturnsNullWhenBufferEmpty(): void
    {
        $channel = new UdpChannel($this->socket, '127.0.0.1', 9999);
        self::assertNull($channel->read());
    }

    public function testCloseDropsBufferAndMarksDead(): void
    {
        $channel = new UdpChannel($this->socket, '127.0.0.1', 9999);
        $channel->feed('payload');
        self::assertTrue($channel->isAlive());
        self::assertTrue($channel->close());
        self::assertFalse($channel->isAlive());
        self::assertNull($channel->read());
        self::assertNull($channel->getResource());
    }

    public function testPeerKey(): void
    {
        $channel = new UdpChannel($this->socket, '10.0.0.5', 1234);
        self::assertSame('10.0.0.5', $channel->getPeerHost());
        self::assertSame(1234, $channel->getPeerPort());
        self::assertSame('10.0.0.5:1234', $channel->peerKey());
    }
}
