<?php

declare(strict_types=1);

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use InitPHP\Socket\Interfaces\SocketConnectionInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;
use Throwable;

use function usleep;

abstract class AbstractServer implements SocketServerInterface
{
    /** @var array<int, SocketConnectionInterface> */
    protected array $clients = [];

    /** @var array<int|string, int> id → internal client key */
    protected array $clientIdMap = [];

    protected int $nextClientKey = 1;

    protected bool $running = false;

    public function __construct(
        protected readonly string $host,
        protected readonly int $port,
    ) {
        if ($host === '') {
            throw new SocketInvalidArgumentException('Server host must not be empty.');
        }
        if ($port <= 0 || $port > 65535) {
            throw new SocketInvalidArgumentException('Server port must be between 1 and 65535.');
        }
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getClients(): array
    {
        /** @var array<int|string, SocketConnectionInterface> $map */
        $map = [];
        foreach ($this->clients as $key => $client) {
            $id = $client->getId();
            $map[$id ?? $key] = $client;
        }

        return $map;
    }

    public function register(int|string $id, SocketConnectionInterface $client): bool
    {
        $key = $this->indexOf($client);
        if ($key === null) {
            return false;
        }
        $this->clientIdMap[$id] = $key;
        $client->setId($id);

        return true;
    }

    public function broadcast(string $message, int|string|array|null $clients = null): bool
    {
        try {
            if ($clients === null) {
                foreach ($this->clients as $client) {
                    if ($client->isAlive()) {
                        $client->write($message);
                    }
                }

                return true;
            }
            $ids = \is_array($clients) ? $clients : [$clients];
            foreach ($ids as $id) {
                if (!isset($this->clientIdMap[$id])) {
                    continue;
                }
                $key = $this->clientIdMap[$id];
                $client = $this->clients[$key] ?? null;
                if ($client !== null && $client->isAlive()) {
                    $client->write($message);
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function wait(float $seconds): void
    {
        if ($seconds < 0) {
            throw new SocketInvalidArgumentException('Waiting time cannot be negative.');
        }
        if ($seconds === 0.0) {
            return;
        }
        usleep((int) ($seconds * 1_000_000));
    }

    abstract public function listen(): static;

    abstract public function close(): bool;

    abstract public function tick(callable $callback, float $waitSeconds = 0.0): int;

    abstract public function getSocket(): mixed;

    public function live(callable $callback, float $idleSeconds = 0.05): void
    {
        $this->running = true;
        while ($this->isRunning()) {
            $this->tick($callback, $idleSeconds);
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Register a newly accepted connection. Returns its internal key.
     */
    protected function addClient(SocketConnectionInterface $client): int
    {
        $key = $this->nextClientKey++;
        $this->clients[$key] = $client;

        return $key;
    }

    /**
     * Drop a client from every registry. Does not close it — callers must
     * close first if needed.
     */
    protected function evict(int $key): void
    {
        unset($this->clients[$key]);
        foreach ($this->clientIdMap as $id => $mappedKey) {
            if ($mappedKey === $key) {
                unset($this->clientIdMap[$id]);
            }
        }
    }

    protected function indexOf(SocketConnectionInterface $client): ?int
    {
        foreach ($this->clients as $key => $existing) {
            if ($existing === $client) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Split a fractional second count into (seconds, microseconds) for
     * the *_select() family of functions.
     *
     * @return array{0: int, 1: int}
     */
    protected static function splitSeconds(float $seconds): array
    {
        if ($seconds < 0) {
            $seconds = 0.0;
        }
        $whole = (int) $seconds;
        $usec = (int) (($seconds - $whole) * 1_000_000);

        return [$whole, $usec];
    }
}
