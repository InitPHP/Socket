<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\TCP as TcpClient;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;
use InitPHP\Socket\Server\TCP as TcpServer;

final class TcpEchoTest extends IntegrationTestCase
{
    public function testServerAcceptsClientAndEchoesData(): void
    {
        $port = $this->findFreePort();

        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $client = new TcpClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        // First tick: pick up the new connection from the listen backlog.
        $server->tick(static fn () => null, 0.5);
        self::assertCount(1, $server->getClients());

        // Client → server payload.
        self::assertSame(11, $client->write('hello-world'));

        // Second tick: server reads the inbound bytes and echoes them back.
        $received = null;
        $server->tick(
            static function (SocketServerInterface $srv, SocketConnectionInterface $conn) use (&$received): void {
                $received = $conn->read(1024);
                $conn->write('echo:' . (string) $received);
            },
            0.5,
        );

        self::assertSame('hello-world', $received);

        // Briefly wait for the kernel to flush the echo back to the client side.
        $reply = null;
        for ($i = 0; $i < 20 && $reply === null; ++$i) {
            $reply = $client->read(1024);
            if ($reply === null) {
                usleep(10_000);
            }
        }
        self::assertSame('echo:hello-world', $reply);
    }

    public function testBroadcastReachesEveryConnectedClient(): void
    {
        $port = $this->findFreePort();

        $server = new TcpServer('127.0.0.1', $port);
        $server->listen();
        $this->registerCleanup($server->close(...));

        $clientA = new TcpClient('127.0.0.1', $port);
        $clientB = new TcpClient('127.0.0.1', $port);
        $clientA->connect();
        $clientB->connect();
        $this->registerCleanup($clientA->disconnect(...));
        $this->registerCleanup($clientB->disconnect(...));

        // Accept both pending connections.
        $server->tick(static fn () => null, 0.2);
        $server->tick(static fn () => null, 0.2);
        self::assertCount(2, $server->getClients());

        self::assertTrue($server->broadcast('beacon'));

        // Drain both clients.
        $readA = $this->awaitRead($clientA);
        $readB = $this->awaitRead($clientB);
        self::assertSame('beacon', $readA);
        self::assertSame('beacon', $readB);
    }

    private function awaitRead(TcpClient $client): ?string
    {
        for ($i = 0; $i < 50; ++$i) {
            $chunk = $client->read(1024);
            if ($chunk !== null) {
                return $chunk;
            }
            usleep(10_000);
        }

        return null;
    }
}
