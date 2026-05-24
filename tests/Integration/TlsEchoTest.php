<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use InitPHP\Socket\Client\TLS as TlsClient;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;
use InitPHP\Socket\Server\TLS as TlsServer;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pcntl')]
#[RequiresPhpExtension('posix')]
final class TlsEchoTest extends IntegrationTestCase
{
    public function testEncryptedRoundTripWithSelfSignedCertificate(): void
    {
        $port = $this->findFreePort();
        $certPath = $this->selfSignedCertPath();

        // The TLS server must run in the parent process so its code paths
        // show up in our coverage report. We fork the *client* into a
        // child process instead — its job is to drive the handshake and
        // exchange one message.
        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid, 'pcntl_fork failed');

        if ($pid === 0) {
            $exitCode = $this->runClientChild($port);
            // Hard-exit so PHPUnit shutdown handlers don't run in the child.
            exit($exitCode);
        }

        try {
            $server = (new TlsServer('127.0.0.1', $port, 2.0))
                ->option('local_cert', $certPath)
                ->option('allow_self_signed', true)
                ->option('verify_peer', false);
            $server->listen();

            $received = null;
            $deadline = microtime(true) + 5.0;
            while ($received === null && microtime(true) < $deadline) {
                $server->tick(
                    static function (SocketServerInterface $srv, SocketConnectionInterface $conn) use (&$received): void {
                        $payload = $conn->read(1024);
                        if ($payload !== null) {
                            $received = $payload;
                            $conn->write('echo:' . $payload);
                        }
                    },
                    0.1,
                );
            }
            $server->close();

            $status = 0;
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status), 'client child did not exit cleanly');
            self::assertSame(0, pcntl_wexitstatus($status), 'client child reported failure');

            self::assertSame('hello-tls', $received);
        } finally {
            if (posix_kill($pid, 0)) {
                posix_kill($pid, \SIGTERM);
                pcntl_waitpid($pid, $_status);
            }
        }
    }

    private function runClientChild(int $port): int
    {
        try {
            // Give the parent a beat to bind before we connect.
            usleep(150_000);

            $client = (new TlsClient('127.0.0.1', $port, 2.0))
                ->option('verify_peer', false)
                ->option('verify_peer_name', false)
                ->option('allow_self_signed', true);
            $client->connect();
            $client->write('hello-tls');

            $reply = null;
            for ($i = 0; $i < 200 && $reply === null; ++$i) {
                $reply = $client->read(1024);
                if ($reply === null) {
                    usleep(20_000);
                }
            }
            $client->disconnect();

            return $reply === 'echo:hello-tls' ? 0 : 11;
        } catch (\Throwable $e) {
            fwrite(\STDERR, 'tls client child error: ' . $e->getMessage() . "\n");

            return 1;
        }
    }
}
