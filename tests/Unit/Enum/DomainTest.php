<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Enum;

use InitPHP\Socket\Enum\Domain;
use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Domain::class)]
final class DomainTest extends TestCase
{
    public function testToAddressFamilyMapping(): void
    {
        self::assertSame(\AF_INET, Domain::V4->toAddressFamily());
        self::assertSame(\AF_INET6, Domain::V6->toAddressFamily());
        self::assertSame(\AF_UNIX, Domain::UNIX->toAddressFamily());
    }

    public function testFromNameAcceptsKnownStrings(): void
    {
        self::assertSame(Domain::V4, Domain::fromName('v4'));
        self::assertSame(Domain::V6, Domain::fromName('V6'));
        self::assertSame(Domain::UNIX, Domain::fromName('unix'));
    }

    public function testFromNameDefaultsToV4WhenNullOrEmpty(): void
    {
        self::assertSame(Domain::V4, Domain::fromName(null));
        self::assertSame(Domain::V4, Domain::fromName(''));
    }

    public function testFromNameRejectsUnknown(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        Domain::fromName('ipx');
    }
}
