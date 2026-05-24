<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\UDP as UdpClient;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;
use InitPHP\Socket\Server\UDP as UdpServer;

final class UdpEchoTest extends IntegrationTestCase
{
    public function testServerEchoesDatagramBackToSender(): void
    {
        $port = $this->findFreePort(\SOCK_DGRAM);

        $server = new UdpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new UdpClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        self::assertSame(5, $client->write('hello'));

        $received = null;
        $server->tick(
            static function (SocketServerInterface $srv, SocketConnectionInterface $conn) use (&$received): void {
                $received = $conn->read(65535);
                $conn->write('echo:' . (string) $received);
            },
            0.5,
        );

        self::assertSame('hello', $received);
        self::assertCount(1, $server->getClients());

        $reply = null;
        for ($i = 0; $i < 20 && $reply === null; ++$i) {
            $reply = $client->read(1024);
            if ($reply === null) {
                usleep(10_000);
            }
        }
        self::assertSame('echo:hello', $reply);
    }

    public function testServerMaintainsSeparateConnectionsPerPeer(): void
    {
        $port = $this->findFreePort(\SOCK_DGRAM);
        $server = new UdpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $clientA = new UdpClient('127.0.0.1', $port);
        $clientB = new UdpClient('127.0.0.1', $port);
        $clientA->connect();
        $clientB->connect();
        $this->registerCleanup($clientA->disconnect(...));
        $this->registerCleanup($clientB->disconnect(...));

        $clientA->write('from-a');
        $clientB->write('from-b');

        $messages = [];
        $cb = static function (SocketServerInterface $srv, SocketConnectionInterface $conn) use (&$messages): void {
            $payload = $conn->read(65535);
            if ($payload !== null) {
                $messages[] = $payload;
            }
        };
        $server->tick($cb, 0.5);
        $server->tick($cb, 0.5);

        sort($messages);
        self::assertSame(['from-a', 'from-b'], $messages);
        self::assertCount(2, $server->getClients());
    }
}
