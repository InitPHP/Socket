<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Enum;

use InitPHP\Socket\Enum\CryptoMethod;
use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CryptoMethod::class)]
final class CryptoMethodTest extends TestCase
{
    public function testEveryCaseExposesClientAndServerConstants(): void
    {
        foreach (CryptoMethod::cases() as $case) {
            self::assertGreaterThanOrEqual(0, $case->forClient());
            self::assertGreaterThanOrEqual(0, $case->forServer());
        }
    }

    public function testFromNameIsCaseInsensitive(): void
    {
        self::assertSame(CryptoMethod::TLS, CryptoMethod::fromName('TLS'));
        self::assertSame(CryptoMethod::TLSv1_2, CryptoMethod::fromName('tlsv1.2'));
    }

    public function testFromNameRejectsUnknown(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        CryptoMethod::fromName('tlsv9');
    }
}
