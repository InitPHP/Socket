<?php

declare(strict_types=1);

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Channel\TcpChannel;
use InitPHP\Socket\Enum\Domain;
use InitPHP\Socket\Exception\SocketConnectionException;
use InitPHP\Socket\Exception\SocketException;
use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use InitPHP\Socket\Exception\SocketListenException;
use Socket;

use function getprotobyname;
use function socket_accept;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_listen;
use function socket_select;
use function socket_set_nonblock;
use function socket_strerror;

use const SOCK_STREAM;
use const SOCKET_EINTR;

final class TCP extends AbstractServer
{
    private ?Socket $listenSocket = null;

    private int $backlog = 8;

    public function __construct(
        string $host,
        int $port,
        private readonly Domain $domain = Domain::V4,
    ) {
        parent::__construct($host, $port);
    }

    public function backlog(int $backlog): self
    {
        if ($backlog < 1) {
            throw new SocketInvalidArgumentException('backlog must be at least 1.');
        }
        $this->backlog = $backlog;

        return $this;
    }

    public function listen(): static
    {
        if ($this->listenSocket !== null) {
            throw new SocketException('Server is already listening.');
        }
        $proto = getprotobyname('tcp');
        $socket = @socket_create($this->domain->toAddressFamily(), SOCK_STREAM, $proto === false ? 0 : $proto);
        if (!$socket instanceof Socket) {
            throw new SocketException('socket_create failed: ' . socket_strerror(socket_last_error()));
        }
        if (@socket_bind($socket, $this->host, $this->port) === false) {
            $err = socket_strerror(socket_last_error($socket));
            socket_close($socket);
            throw new SocketException('socket_bind failed: ' . $err);
        }
        if (@socket_listen($socket, $this->backlog) === false) {
            $err = socket_strerror(socket_last_error($socket));
            socket_close($socket);
            throw new SocketListenException('socket_listen failed: ' . $err);
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
        $read = $this->buildReadSet();
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
        $events = 0;
        foreach ($read as $readableSocket) {
            if ($readableSocket === $this->listenSocket) {
                $this->acceptNew();
                ++$events;
                continue;
            }
            $this->serviceReadable($readableSocket, $callback);
            ++$events;
        }

        return $events;
    }

    public function getSocket(): ?Socket
    {
        return $this->listenSocket;
    }

    /**
     * @return array<int, Socket>
     */
    private function buildReadSet(): array
    {
        $set = [];
        if ($this->listenSocket !== null) {
            $set[] = $this->listenSocket;
        }
        foreach ($this->clients as $client) {
            $resource = $client->getSocket();
            if ($resource instanceof Socket) {
                $set[] = $resource;
            }
        }

        return $set;
    }

    private function acceptNew(): void
    {
        if ($this->listenSocket === null) {
            return;
        }
        $accepted = @socket_accept($this->listenSocket);
        if (!$accepted instanceof Socket) {
            $err = socket_last_error($this->listenSocket);
            if ($err === 0 || $err === SOCKET_EINTR) {
                return;
            }
            throw new SocketConnectionException('socket_accept failed: ' . socket_strerror($err));
        }
        @socket_set_nonblock($accepted);
        $this->addClient(new ServerConnection(new TcpChannel($accepted)));
    }

    /**
     * @param callable(\InitPHP\Socket\Interfaces\SocketServerInterface, \InitPHP\Socket\Interfaces\SocketConnectionInterface): void $callback
     */
    private function serviceReadable(Socket $socket, callable $callback): void
    {
        $key = $this->findKeyBySocket($socket);
        if ($key === null) {
            return;
        }
        $client = $this->clients[$key];
        if (!$client->isAlive()) {
            $client->close();
            $this->evict($key);

            return;
        }
        $callback($this, $client);
    }

    private function findKeyBySocket(Socket $socket): ?int
    {
        foreach ($this->clients as $key => $client) {
            if ($client->getSocket() === $socket) {
                return $key;
            }
        }

        return null;
    }
}
