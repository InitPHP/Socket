<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\UDP as UdpClient;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;
use InitPHP\Socket\Server\UDP as UdpServer;

final class UdpServerCloseTest extends IntegrationTestCase
{
    public function testCloseTearsDownEveryConnection(): void
    {
        $port = $this->findFreePort(\SOCK_DGRAM);
        $server = new UdpServer('127.0.0.1', $port);
        $server->listen();

        $client = new UdpClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        $client->write('greet');
        $server->tick(static function () {
        }, 0.3);
        self::assertCount(1, $server->getClients());

        self::assertTrue($server->close());
        self::assertSame([], $server->getClients());
        self::assertNull($server->getSocket());
    }

    public function testBroadcastReachesEveryKnownPeer(): void
    {
        $port = $this->findFreePort(\SOCK_DGRAM);
        $server = new UdpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $alice = new UdpClient('127.0.0.1', $port);
        $bob = new UdpClient('127.0.0.1', $port);
        $alice->connect();
        $bob->connect();
        $this->registerCleanup($alice->disconnect(...));
        $this->registerCleanup($bob->disconnect(...));

        $alice->write('hi');
        $bob->write('hi');

        $cb = static function (SocketServerInterface $srv, SocketConnectionInterface $conn): void {
            $conn->read(65535);
        };
        $server->tick($cb, 0.3);
        $server->tick($cb, 0.3);
        self::assertCount(2, $server->getClients());

        self::assertTrue($server->broadcast('announce'));

        $heard = 0;
        for ($i = 0; $i < 40; ++$i) {
            if ($alice->read(1024) !== null) {
                ++$heard;
            }
            if ($bob->read(1024) !== null) {
                ++$heard;
            }
            if ($heard >= 2) {
                break;
            }
            usleep(10_000);
        }
        self::assertSame(2, $heard);
    }
}
