<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\UDP as UdpClient;
use InitPHP\Socket\Exception\SocketException;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;
use InitPHP\Socket\Server\UDP as UdpServer;
use Socket;

final class UdpClientReuseTest extends IntegrationTestCase
{
    public function testConnectTwiceThrows(): void
    {
        $port = $this->findFreePort(\SOCK_DGRAM);
        $server = new UdpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new UdpClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        $this->expectException(SocketException::class);
        $client->connect();
    }

    public function testGetSocketReturnsLiveResourceAfterConnect(): void
    {
        $port = $this->findFreePort(\SOCK_DGRAM);
        $server = new UdpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new UdpClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        self::assertInstanceOf(Socket::class, $client->getSocket());
    }

    public function testServerReusesConnectionForReturningPeer(): void
    {
        $port = $this->findFreePort(\SOCK_DGRAM);
        $server = new UdpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new UdpClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        $messages = [];
        $cb = static function (SocketServerInterface $srv, SocketConnectionInterface $conn) use (&$messages): void {
            $messages[] = $conn->read(65535);
        };

        $client->write('one');
        $server->tick($cb, 0.2);
        $client->write('two');
        $server->tick($cb, 0.2);

        // The same peer must produce one client entry, not two.
        self::assertCount(1, $server->getClients());
        self::assertSame(['one', 'two'], $messages);
    }
}
