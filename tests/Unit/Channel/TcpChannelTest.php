<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Channel;

use InitPHP\Socket\Channel\TcpChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Socket;

#[CoversClass(TcpChannel::class)]
final class TcpChannelTest extends TestCase
{
    /** @var array<int, Socket> */
    private array $pair = [];

    protected function setUp(): void
    {
        $pair = [];
        self::assertTrue(socket_create_pair(\AF_UNIX, \SOCK_STREAM, 0, $pair));
        $this->pair = $pair;
        // Make the peer non-blocking so isAlive's MSG_DONTWAIT behaves predictably.
        socket_set_nonblock($this->pair[0]);
        socket_set_nonblock($this->pair[1]);
    }

    protected function tearDown(): void
    {
        foreach ($this->pair as $sock) {
            if ($sock instanceof Socket) {
                @socket_close($sock);
            }
        }
    }

    public function testRoundTripWriteAndRead(): void
    {
        $channel = new TcpChannel($this->pair[0]);
        $remote = $this->pair[1];

        $bytes = $channel->write('payload');
        self::assertSame(7, $bytes);

        $received = '';
        $read = socket_recv($remote, $received, 1024, \MSG_DONTWAIT);
        self::assertSame(7, $read);
        self::assertSame('payload', $received);
    }

    public function testReadFromPeerWrite(): void
    {
        $channel = new TcpChannel($this->pair[0]);
        socket_send($this->pair[1], 'hi', 2, 0);
        // Tiny wait to let the kernel deliver the bytes.
        usleep(20_000);
        self::assertSame('hi', $channel->read(1024));
    }

    public function testReadReturnsNullWhenNoDataAvailable(): void
    {
        $channel = new TcpChannel($this->pair[0]);
        self::assertNull($channel->read(1024));
    }

    public function testIsAliveStaysTrueWithNoTrafficAndFlipsAfterPeerClose(): void
    {
        $channel = new TcpChannel($this->pair[0]);
        self::assertTrue($channel->isAlive());

        @socket_close($this->pair[1]);
        // Remove from the cleanup set so tearDown doesn't double-close.
        unset($this->pair[1]);

        self::assertFalse($channel->isAlive());
    }

    public function testCloseFreesResourceAndIsIdempotent(): void
    {
        $channel = new TcpChannel($this->pair[0]);
        self::assertSame($this->pair[0], $channel->getResource());

        self::assertTrue($channel->close());
        self::assertNull($channel->getResource());
        self::assertFalse($channel->isAlive());
        self::assertNull($channel->read(1024));
        self::assertNull($channel->write('x'));
        // Second close is a no-op.
        self::assertTrue($channel->close());
        // Avoid double-closing in tearDown.
        unset($this->pair[0]);
    }
}
