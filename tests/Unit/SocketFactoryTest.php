<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit;

use InitPHP\Socket\Client\SSL as SslClient;
use InitPHP\Socket\Client\TCP as TcpClient;
use InitPHP\Socket\Client\TLS as TlsClient;
use InitPHP\Socket\Client\UDP as UdpClient;
use InitPHP\Socket\Enum\Domain;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use InitPHP\Socket\Server\SSL as SslServer;
use InitPHP\Socket\Server\TCP as TcpServer;
use InitPHP\Socket\Server\TLS as TlsServer;
use InitPHP\Socket\Server\UDP as UdpServer;
use InitPHP\Socket\Socket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Socket::class)]
final class SocketFactoryTest extends TestCase
{
    public function testServerFactoryReturnsConcreteForEachTransport(): void
    {
        self::assertInstanceOf(TcpServer::class, Socket::server(Transport::TCP, '127.0.0.1', 0xFEED, Domain::V4));
        self::assertInstanceOf(UdpServer::class, Socket::server(Transport::UDP, '127.0.0.1', 0xFEED, Domain::V4));
        self::assertInstanceOf(TlsServer::class, Socket::server(Transport::TLS, '127.0.0.1', 0xFEED));
        self::assertInstanceOf(SslServer::class, Socket::server(Transport::SSL, '127.0.0.1', 0xFEED));
    }

    public function testClientFactoryReturnsConcreteForEachTransport(): void
    {
        self::assertInstanceOf(TcpClient::class, Socket::client(Transport::TCP, '127.0.0.1', 0xFEED, Domain::V4));
        self::assertInstanceOf(UdpClient::class, Socket::client(Transport::UDP, '127.0.0.1', 0xFEED, Domain::V4));
        self::assertInstanceOf(TlsClient::class, Socket::client(Transport::TLS, '127.0.0.1', 0xFEED));
        self::assertInstanceOf(SslClient::class, Socket::client(Transport::SSL, '127.0.0.1', 0xFEED));
    }

    public function testEmptyHostRejected(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        Socket::server(Transport::TCP, '', 80);
    }

    public function testPortOutOfRangeRejected(): void
    {
        $this->expectException(SocketInvalidArgumentException::class);
        Socket::client(Transport::TCP, '127.0.0.1', 70000);
    }
}
