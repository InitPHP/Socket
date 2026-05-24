<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\TCP as TcpClient;
use InitPHP\Socket\Exception\SocketConnectionException;
use InitPHP\Socket\Exception\SocketException;
use InitPHP\Socket\Server\TCP as TcpServer;
use InitPHP\Socket\Server\UDP as UdpServer;

final class ConnectionErrorsTest extends IntegrationTestCase
{
    public function testTcpClientConnectFailsWhenPortIsClosed(): void
    {
        // Bind+release a port to find one we are confident is unbound.
        $port = $this->findFreePort();

        $client = new TcpClient('127.0.0.1', $port);
        $this->expectException(SocketConnectionException::class);
        $client->connect();
    }

    public function testTcpServerListenFailsOnPortConflict(): void
    {
        $port = $this->findFreePort();

        $a = new TcpServer('127.0.0.1', $port);
        $a->listen();
        $this->registerCleanup($a->close(...));

        $b = new TcpServer('127.0.0.1', $port);
        $this->expectException(SocketException::class);
        $b->listen();
    }

    public function testUdpServerListenFailsOnPortConflict(): void
    {
        $port = $this->findFreePort(\SOCK_DGRAM);

        $a = new UdpServer('127.0.0.1', $port);
        $a->listen();
        $this->registerCleanup($a->close(...));

        $b = new UdpServer('127.0.0.1', $port);
        $this->expectException(SocketException::class);
        $b->listen();
    }

    public function testTcpClientWriteReadAfterDisconnectReturnsNull(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new TcpClient('127.0.0.1', $port);
        $client->connect();
        $client->disconnect();

        self::assertNull($client->write('x'));
        self::assertNull($client->read(64));
        self::assertNull($client->getSocket());
    }

    public function testTcpServerConnectTwiceThrows(): void
    {
        $port = $this->findFreePort();
        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new TcpClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        $this->expectException(SocketException::class);
        $client->connect();
    }
}
