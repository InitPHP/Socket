<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Server;

use InitPHP\Socket\Enum\CryptoMethod;
use InitPHP\Socket\Exception\SocketException;
use InitPHP\Socket\Server\TLS as TlsServer;
use PHPUnit\Framework\TestCase;

final class AbstractStreamServerTest extends TestCase
{
    public function testOptionTimeoutBlockingCryptoChain(): void
    {
        $server = new TlsServer('127.0.0.1', 9443);
        self::assertSame(
            $server,
            $server->option('local_cert', '/tmp/x.pem')
                ->timeout(1.5)
                ->blocking(false)
                ->crypto(CryptoMethod::TLSv1_2),
        );
    }

    public function testCryptoNullClearsContextOption(): void
    {
        $server = new TlsServer('127.0.0.1', 9443);
        $server->crypto(CryptoMethod::TLSv1_2);
        self::assertSame(
            $server,
            $server->crypto(null),
        );
    }

    public function testCloseBeforeListenIsIdempotent(): void
    {
        $server = new TlsServer('127.0.0.1', 9443);
        self::assertTrue($server->close());
        self::assertTrue($server->close());
        self::assertNull($server->getSocket());
    }

    public function testTickBeforeListenThrows(): void
    {
        $server = new TlsServer('127.0.0.1', 9443);
        $this->expectException(SocketException::class);
        $server->tick(static fn () => null, 0.0);
    }

}
