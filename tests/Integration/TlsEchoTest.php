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

        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid, 'pcntl_fork failed');

        if ($pid === 0) {
            $exitCode = $this->runServerChild($port, $certPath);
            // Hard-exit so PHPUnit shutdown handlers don't run in the child.
            exit($exitCode);
        }

        try {
            // Give the child a moment to bind before we connect.
            usleep(150_000);

            $client = (new TlsClient('127.0.0.1', $port, 2.0))
                ->option('verify_peer', false)
                ->option('verify_peer_name', false)
                ->option('allow_self_signed', true);
            $client->connect();

            self::assertSame(9, $client->write('hello-tls'));

            $reply = $this->awaitRead($client);
            $client->disconnect();

            $status = 0;
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status), 'child did not exit cleanly');
            self::assertSame(0, pcntl_wexitstatus($status), 'server child reported failure');

            self::assertSame('echo:hello-tls', $reply);
        } finally {
            if (posix_kill($pid, 0)) {
                posix_kill($pid, \SIGTERM);
                pcntl_waitpid($pid, $_status);
            }
        }
    }

    private function runServerChild(int $port, string $certPath): int
    {
        try {
            $server = (new TlsServer('127.0.0.1', $port, 2.0))
                ->option('local_cert', $certPath)
                ->option('allow_self_signed', true)
                ->option('verify_peer', false);
            $server->listen();

            $deadline = microtime(true) + 4.0;
            $handled = false;
            while (!$handled && microtime(true) < $deadline) {
                $server->tick(
                    static function (SocketServerInterface $srv, SocketConnectionInterface $conn) use (&$handled): void {
                        $data = $conn->read(1024);
                        if ($data !== null) {
                            $conn->write('echo:' . $data);
                            $handled = true;
                        }
                    },
                    0.05,
                );
            }
            $server->close();

            return $handled ? 0 : 10;
        } catch (\Throwable $e) {
            fwrite(\STDERR, 'tls server child error: ' . $e->getMessage() . "\n");

            return 1;
        }
    }

    private function awaitRead(TlsClient $client): ?string
    {
        for ($i = 0; $i < 100; ++$i) {
            $chunk = $client->read(1024);
            if ($chunk !== null) {
                return $chunk;
            }
            usleep(20_000);
        }

        return null;
    }
}
