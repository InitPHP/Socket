<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Channel;

use InitPHP\Socket\Channel\StreamChannel;
use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamChannel::class)]
final class StreamChannelTest extends TestCase
{
    public function testConstructorRejectsNonResource(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        /** @phpstan-ignore-next-line argument.type */
        new StreamChannel('not a resource');
    }

    public function testWriteAndReadOnAMemoryStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);

        $channel = new StreamChannel($stream);
        self::assertSame(5, $channel->write('hello'));

        rewind($stream);
        self::assertSame('hello', $channel->read(1024));
        // EOF after draining
        self::assertNull($channel->read(1024));
    }

    public function testReadRejectsZeroLength(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);

        $channel = new StreamChannel($stream);
        self::assertNull($channel->read(0));
    }

    public function testIsAliveTracksResourceState(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);

        $channel = new StreamChannel($stream);
        self::assertTrue($channel->isAlive());
        fclose($stream);
        self::assertFalse($channel->isAlive());
    }

    public function testCloseIsIdempotent(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);

        $channel = new StreamChannel($stream);
        self::assertTrue($channel->close());
        self::assertNull($channel->getResource());
        self::assertFalse($channel->isAlive());
        self::assertNull($channel->read(1024));
        self::assertNull($channel->write('x'));
        self::assertTrue($channel->close());
    }
}
