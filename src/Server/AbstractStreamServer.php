<?php

declare(strict_types=1);

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Channel\StreamChannel;
use InitPHP\Socket\Enum\CryptoMethod;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Exception\SocketException;
use InitPHP\Socket\Exception\SocketListenException;

use function fclose;
use function stream_context_create;
use function stream_select;
use function stream_set_blocking;
use function stream_set_timeout;
use function stream_socket_accept;
use function stream_socket_enable_crypto;
use function stream_socket_server;

use const STREAM_SERVER_BIND;
use const STREAM_SERVER_LISTEN;

abstract class AbstractStreamServer extends AbstractServer
{
    /** @var resource|null */
    private $listenSocket;

    /** @var array<string, mixed> SSL context options */
    protected array $options = [];

    protected ?float $timeout = null;

    protected ?CryptoMethod $crypto = null;

    protected bool $blocking = false;

    public function __construct(
        string $host,
        int $port,
        protected readonly Transport $transport,
        ?float $timeout = null,
    ) {
        parent::__construct($host, $port);
        $this->timeout = $timeout;
    }

    /**
     * Set an SSL stream context option.
     *
     * @see https://www.php.net/manual/en/context.ssl.php
     */
    public function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function timeout(float $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function blocking(bool $mode = true): static
    {
        $this->blocking = $mode;

        return $this;
    }

    public function crypto(?CryptoMethod $method): static
    {
        $this->crypto = $method;
        if ($method !== null) {
            $this->options['crypto_method'] = $method->forServer();
        } else {
            unset($this->options['crypto_method']);
        }

        return $this;
    }

    public function listen(): static
    {
        if ($this->listenSocket !== null) {
            throw new SocketException('Server is already listening.');
        }
        $address = $this->transport->scheme() . '://' . $this->host . ':' . $this->port;
        $errNo = 0;
        $errStr = '';
        $context = stream_context_create(['ssl' => $this->options]);
        $socket = @stream_socket_server(
            $address,
            $errNo,
            $errStr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context,
        );
        if ($socket === false) {
            throw new SocketListenException(
                \sprintf('stream_socket_server failed (%d): %s', $errNo, $errStr !== '' ? $errStr : 'unknown error'),
            );
        }
        // We deliberately leave the listening stream in its default blocking mode:
        // stream_select() drives readiness, and a non-blocking listen prevents
        // stream_socket_accept() from completing the TLS/SSL handshake within
        // the timeout we pass.
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
        if (\is_resource($this->listenSocket)) {
            @fclose($this->listenSocket);
        }
        $this->listenSocket = null;
        $this->running = false;

        return true;
    }

    public function tick(callable $callback, float $waitSeconds = 0.0): int
    {
        if (!\is_resource($this->listenSocket)) {
            throw new SocketException('Server is not listening. Call listen() first.');
        }
        [$sec, $usec] = self::splitSeconds($waitSeconds);
        $read = $this->buildReadSet();
        $write = null;
        $except = null;
        $ready = @stream_select($read, $write, $except, $sec, $usec);
        if ($ready === false) {
            throw new SocketException('stream_select failed.');
        }
        if ($ready === 0) {
            return 0;
        }
        $events = 0;
        foreach ($read as $resource) {
            if ($resource === $this->listenSocket) {
                $this->acceptNew();
                ++$events;
                continue;
            }
            $this->serviceReadable($resource, $callback);
            ++$events;
        }

        return $events;
    }

    public function getSocket(): mixed
    {
        return $this->listenSocket;
    }

    /**
     * @return array<int, resource>
     */
    private function buildReadSet(): array
    {
        $set = [];
        if (\is_resource($this->listenSocket)) {
            $set[] = $this->listenSocket;
        }
        foreach ($this->clients as $client) {
            $resource = $client->getSocket();
            if (\is_resource($resource)) {
                $set[] = $resource;
            }
        }

        return $set;
    }

    private function acceptNew(): void
    {
        if (!\is_resource($this->listenSocket)) {
            return;
        }
        // Always give the accept call enough room for the TLS/SSL handshake.
        // select() has already told us a client is queued, so this won't sit
        // idly — it bounds the handshake itself.
        $handshakeTimeout = $this->timeout !== null && $this->timeout > 0 ? $this->timeout : 1.0;
        $accepted = @stream_socket_accept($this->listenSocket, $handshakeTimeout);
        if ($accepted === false || !\is_resource($accepted)) {
            return;
        }
        stream_set_blocking($accepted, $this->blocking);
        if ($this->timeout !== null) {
            stream_set_timeout($accepted, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1_000_000));
        }
        if ($this->crypto !== null) {
            @stream_socket_enable_crypto($accepted, true, $this->crypto->forServer());
        }
        $this->addClient(new ServerConnection(new StreamChannel($accepted)));
    }

    /**
     * @param resource $resource
     * @param callable(\InitPHP\Socket\Interfaces\SocketServerInterface, \InitPHP\Socket\Interfaces\SocketConnectionInterface): void $callback
     */
    private function serviceReadable($resource, callable $callback): void
    {
        $key = $this->findKeyByResource($resource);
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

    /**
     * @param resource $resource
     */
    private function findKeyByResource($resource): ?int
    {
        foreach ($this->clients as $key => $client) {
            if ($client->getSocket() === $resource) {
                return $key;
            }
        }

        return null;
    }
}
