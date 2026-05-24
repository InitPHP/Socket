<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Socket;

abstract class IntegrationTestCase extends TestCase
{
    /**
     * Bind a transient socket of the requested type to ephemeral port 0
     * and return the port that the OS assigned. The probe is closed
     * before returning so the caller can reuse the port.
     *
     * There is a tiny race window between this call and the caller's
     * own bind() — acceptable for localhost test scaffolding.
     */
    protected function findFreePort(int $type = \SOCK_STREAM): int
    {
        $proto = $type === \SOCK_STREAM ? \SOL_TCP : \SOL_UDP;
        $sock = socket_create(\AF_INET, $type, $proto);
        self::assertInstanceOf(Socket::class, $sock);
        self::assertTrue(socket_bind($sock, '127.0.0.1', 0));
        $addr = '';
        $port = 0;
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);
        self::assertGreaterThan(0, $port);

        return $port;
    }

    /**
     * Generate a fresh self-signed PEM bundle (cert + key) and return its path.
     * The file is registered for deletion at the end of the test.
     */
    protected function selfSignedCertPath(string $commonName = 'localhost'): string
    {
        $pkey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($pkey, 'openssl_pkey_new failed');
        $csr = openssl_csr_new(['commonName' => $commonName], $pkey);
        self::assertNotFalse($csr, 'openssl_csr_new failed');
        $x509 = openssl_csr_sign($csr, null, $pkey, 1);
        self::assertNotFalse($x509, 'openssl_csr_sign failed');
        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($pkey, $keyPem);

        $path = tempnam(sys_get_temp_dir(), 'initphp-socket-tls-') . '.pem';
        file_put_contents($path, $certPem . $keyPem);
        $this->registerCleanup(static fn (): bool => @unlink($path));

        return $path;
    }

    /** @var array<int, callable(): void> */
    private array $cleanups = [];

    /**
     * @param callable(): mixed $cleanup
     */
    protected function registerCleanup(callable $cleanup): void
    {
        $this->cleanups[] = $cleanup;
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanups) as $cleanup) {
            $cleanup();
        }
        $this->cleanups = [];
    }
}
