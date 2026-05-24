<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Enum;

use InitPHP\Socket\Enum\Transport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Transport::class)]
final class TransportTest extends TestCase
{
    public function testCaseValues(): void
    {
        self::assertSame('tcp', Transport::TCP->value);
        self::assertSame('udp', Transport::UDP->value);
        self::assertSame('tls', Transport::TLS->value);
        self::assertSame('ssl', Transport::SSL->value);
    }

    public function testIsStreamOnlyForTlsAndSsl(): void
    {
        self::assertTrue(Transport::TLS->isStream());
        self::assertTrue(Transport::SSL->isStream());
        self::assertFalse(Transport::TCP->isStream());
        self::assertFalse(Transport::UDP->isStream());
    }

    public function testIsDatagramOnlyForUdp(): void
    {
        self::assertTrue(Transport::UDP->isDatagram());
        self::assertFalse(Transport::TCP->isDatagram());
        self::assertFalse(Transport::TLS->isDatagram());
        self::assertFalse(Transport::SSL->isDatagram());
    }

    public function testSchemeMatchesEnumValue(): void
    {
        foreach (Transport::cases() as $case) {
            self::assertSame($case->value, $case->scheme());
        }
    }
}
