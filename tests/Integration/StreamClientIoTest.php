<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\AbstractStreamClient;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Exception\SocketException;

/**
 * Exercises {@see AbstractStreamClient} end-to-end against a plain `tcp://`
 * stream server in the same process. This skips the TLS handshake — for
 * which we already have {@see TlsEchoTest} — but covers every other
 * stream-client code path (connect, read, write, timeout, blocking,
 * disconnect, crypto-toggle).
 */
final class StreamClientIoTest extends IntegrationTestCase
{
    public function testConnectReadWriteAgainstPlainTcpServer(): void
    {
        $port = $this->findFreePort();
        $errNo = 0;
        $errStr = '';
        $server = stream_socket_server("tcp://127.0.0.1:{$port}", $errNo, $errStr);
        self::assertNotFalse($server, "stream_socket_server failed: {$errStr}");
        $this->registerCleanup(static fn () => @fclose($server));

        $client = new PlainStreamClient('127.0.0.1', $port, 2.0);
        $client->option('verify_peer', false)
            ->timeout(1.5)
            ->blocking(false);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        $peer = stream_socket_accept($server, 1.0);
        self::assertNotFalse($peer);
        $this->registerCleanup(static fn () => @fclose($peer));

        self::assertSame(5, $client->write('hello'));
        // Allow the kernel to deliver.
        usleep(20_000);
        self::assertSame('hello', fread($peer, 1024));

        fwrite($peer, 'world');
        $reply = null;
        for ($i = 0; $i < 30 && $reply === null; ++$i) {
            $reply = $client->read(1024);
            if ($reply === null) {
                usleep(10_000);
            }
        }
        self::assertSame('world', $reply);

        // toggleable after connect — exercises stream_set_blocking + stream_set_timeout branches.
        $client->blocking(true);
        $client->timeout(0.5);
    }

    public function testCryptoCanBeDisabledOnPlainStream(): void
    {
        $port = $this->findFreePort();
        $errNo = 0;
        $errStr = '';
        $server = stream_socket_server("tcp://127.0.0.1:{$port}", $errNo, $errStr);
        self::assertNotFalse($server);
        $this->registerCleanup(static fn () => @fclose($server));

        $client = new PlainStreamClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        // Disabling crypto on a non-encrypted stream is a no-op and must not throw.
        self::assertSame($client, $client->crypto(null));
    }

    public function testConnectTwiceThrows(): void
    {
        $port = $this->findFreePort();
        $errNo = 0;
        $errStr = '';
        $server = stream_socket_server("tcp://127.0.0.1:{$port}", $errNo, $errStr);
        self::assertNotFalse($server);
        $this->registerCleanup(static fn () => @fclose($server));

        $client = new PlainStreamClient('127.0.0.1', $port);
        $client->connect();
        $this->registerCleanup($client->disconnect(...));

        $this->expectException(SocketException::class);
        $client->connect();
    }
}

/**
 * Test-only subclass that drives the abstract stream client over a plain
 * `tcp://` scheme. Lets us cover the abstract's I/O paths in the same
 * process without the cost of a TLS handshake.
 */
final class PlainStreamClient extends AbstractStreamClient
{
    public function __construct(string $host, int $port, ?float $timeout = null)
    {
        parent::__construct($host, $port, Transport::TCP, $timeout);
    }
}
