<?php

declare(strict_types=1);

namespace InitPHP\Socket;

use InitPHP\Socket\Client\SSL as SslClient;
use InitPHP\Socket\Client\TCP as TcpClient;
use InitPHP\Socket\Client\TLS as TlsClient;
use InitPHP\Socket\Client\UDP as UdpClient;
use InitPHP\Socket\Enum\Domain;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Interfaces\SocketClientInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;
use InitPHP\Socket\Server\SSL as SslServer;
use InitPHP\Socket\Server\TCP as TcpServer;
use InitPHP\Socket\Server\TLS as TlsServer;
use InitPHP\Socket\Server\UDP as UdpServer;

/**
 * Factory entry point for the package.
 *
 * Use {@see self::server()} and {@see self::client()} to obtain a transport
 * implementation by enum case rather than constructing concrete classes
 * directly.
 */
final class Socket
{
    private function __construct()
    {
    }

    /**
     * Create a server bound to $host:$port.
     *
     * @param Domain|null $domain Address family for TCP/UDP. Ignored for TLS/SSL.
     * @param float|null $timeout Default socket timeout for TLS/SSL. Ignored for TCP/UDP.
     */
    public static function server(
        Transport $transport,
        string $host,
        int $port,
        ?Domain $domain = null,
        ?float $timeout = null,
    ): SocketServerInterface {
        return match ($transport) {
            Transport::TCP => new TcpServer($host, $port, $domain ?? Domain::V4),
            Transport::UDP => new UdpServer($host, $port, $domain ?? Domain::V4),
            Transport::TLS => new TlsServer($host, $port, $timeout),
            Transport::SSL => new SslServer($host, $port, $timeout),
        };
    }

    /**
     * Create a client targeting $host:$port.
     *
     * @param Domain|null $domain Address family for TCP/UDP. Ignored for TLS/SSL.
     * @param float|null $timeout Connect timeout for TLS/SSL. Ignored for TCP/UDP.
     */
    public static function client(
        Transport $transport,
        string $host,
        int $port,
        ?Domain $domain = null,
        ?float $timeout = null,
    ): SocketClientInterface {
        return match ($transport) {
            Transport::TCP => new TcpClient($host, $port, $domain ?? Domain::V4),
            Transport::UDP => new UdpClient($host, $port, $domain ?? Domain::V4),
            Transport::TLS => new TlsClient($host, $port, $timeout),
            Transport::SSL => new SslClient($host, $port, $timeout),
        };
    }
}
