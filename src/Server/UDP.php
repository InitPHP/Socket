<?php

declare(strict_types=1);

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Channel\UdpChannel;
use InitPHP\Socket\Enum\Domain;
use InitPHP\Socket\Exception\SocketException;
use Socket;

use function getprotobyname;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_recvfrom;
use function socket_select;
use function socket_set_nonblock;
use function socket_strerror;

use const SOCK_DGRAM;
use const SOCKET_EINTR;

final class UDP extends AbstractServer
{
    private ?Socket $listenSocket = null;

    /** @var array<string, int> peer "host:port" → internal client key */
    private array $peerIndex = [];

    /** Default datagram read size. UDP packets cannot exceed 65507 bytes payload. */
    public const MAX_DATAGRAM = 65535;

    public function __construct(
        string $host,
        int $port,
        private readonly Domain $domain = Domain::V4,
    ) {
        parent::__construct($host, $port);
    }

    public function listen(): static
    {
        if ($this->listenSocket !== null) {
            throw new SocketException('Server is already listening.');
        }
        $proto = getprotobyname('udp');
        $socket = @socket_create($this->domain->toAddressFamily(), SOCK_DGRAM, $proto === false ? 0 : $proto);
        if (!$socket instanceof Socket) {
            throw new SocketException('socket_create failed: ' . socket_strerror(socket_last_error()));
        }
        if (@socket_bind($socket, $this->host, $this->port) === false) {
            $err = socket_strerror(socket_last_error($socket));
            socket_close($socket);
            throw new SocketException('socket_bind failed: ' . $err);
        }
        socket_set_nonblock($socket);
        $this->listenSocket = $socket;

        return $this;
    }

    public function close(): bool
    {
        foreach ($this->clients as $client) {
            $client->close();
        }
        $this->clients = [];
        $this->clientIdMap = [];
        $this->peerIndex = [];
        if ($this->listenSocket !== null) {
            @socket_close($this->listenSocket);
            $this->listenSocket = null;
        }
        $this->running = false;

        return true;
    }

    public function tick(callable $callback, float $waitSeconds = 0.0): int
    {
        if ($this->listenSocket === null) {
            throw new SocketException('Server is not listening. Call listen() first.');
        }
        [$sec, $usec] = self::splitSeconds($waitSeconds);
        $read = [$this->listenSocket];
        $write = null;
        $except = null;
        $ready = @socket_select($read, $write, $except, $sec, $usec);
        if ($ready === false) {
            $errno = socket_last_error();
            if ($errno === SOCKET_EINTR) {
                return 0;
            }
            throw new SocketException('socket_select failed: ' . socket_strerror($errno));
        }
        if ($ready === 0) {
            return 0;
        }
        $peerHost = '';
        $peerPort = 0;
        $buf = '';
        $bytes = @socket_recvfrom($this->listenSocket, $buf, self::MAX_DATAGRAM, 0, $peerHost, $peerPort);
        if ($bytes === false || $bytes === 0) {
            return 0;
        }
        $client = $this->resolveOrCreate($peerHost, $peerPort);
        $channel = $client->getChannel();
        if ($channel instanceof UdpChannel) {
            $channel->feed($buf);
        }
        $callback($this, $client);

        return 1;
    }

    public function getSocket(): ?Socket
    {
        return $this->listenSocket;
    }

    private function resolveOrCreate(string $peerHost, int $peerPort): ServerConnection
    {
        $peerKey = $peerHost . ':' . $peerPort;
        if (isset($this->peerIndex[$peerKey])) {
            $clientKey = $this->peerIndex[$peerKey];
            $existing = $this->clients[$clientKey] ?? null;
            if ($existing instanceof ServerConnection) {
                return $existing;
            }
        }
        $channel = new UdpChannel($this->listenSocket(), $peerHost, $peerPort);
        $connection = new ServerConnection($channel);
        $clientKey = $this->addClient($connection);
        $this->peerIndex[$peerKey] = $clientKey;

        return $connection;
    }

    private function listenSocket(): Socket
    {
        if ($this->listenSocket === null) {
            throw new SocketException('Server is not listening.');
        }

        return $this->listenSocket;
    }
}
