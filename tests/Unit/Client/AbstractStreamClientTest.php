<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Client;

use InitPHP\Socket\Client\TLS as TlsClient;
use InitPHP\Socket\Enum\CryptoMethod;
use InitPHP\Socket\Exception\SocketConnectionException;
use InitPHP\Socket\Exception\SocketException;
use PHPUnit\Framework\TestCase;

final class AbstractStreamClientTest extends TestCase
{
    public function testOptionTimeoutBlockingAreChainable(): void
    {
        $client = new TlsClient('127.0.0.1', 9443);
        self::assertSame(
            $client,
            $client->option('verify_peer', false)
                ->option('verify_peer_name', false)
                ->timeout(2.5)
                ->blocking(false),
        );
    }

    public function testCryptoBeforeConnectThrows(): void
    {
        $client = new TlsClient('127.0.0.1', 9443);
        $this->expectException(SocketException::class);
        $client->crypto(CryptoMethod::TLSv1_2);
    }

    public function testReadAndWriteReturnNullBeforeConnect(): void
    {
        $client = new TlsClient('127.0.0.1', 9443);
        self::assertNull($client->read(1024));
        self::assertNull($client->write('payload'));
        self::assertNull($client->getSocket());
        self::assertTrue($client->disconnect());
    }

    public function testConnectFailsWithoutListener(): void
    {
        // Bind a port and immediately release it so we have a high-confidence
        // "no listener here" target without racing other tests.
        $sock = socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);
        self::assertNotFalse($sock);
        socket_bind($sock, '127.0.0.1', 0);
        $addr = '';
        $port = 0;
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);

        $client = new TlsClient('127.0.0.1', $port, 0.3);
        $this->expectException(SocketConnectionException::class);
        $client->connect();
    }
}
