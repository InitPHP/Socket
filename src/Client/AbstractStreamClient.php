<?php

declare(strict_types=1);

namespace InitPHP\Socket\Client;

use InitPHP\Socket\Enum\CryptoMethod;
use InitPHP\Socket\Enum\Transport;
use InitPHP\Socket\Exception\SocketConnectionException;
use InitPHP\Socket\Exception\SocketException;

use function fclose;
use function feof;
use function fread;
use function fwrite;
use function stream_context_create;
use function stream_set_blocking;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_enable_crypto;

use const STREAM_CLIENT_CONNECT;

abstract class AbstractStreamClient extends AbstractClient
{
    /** @var resource|null */
    private $stream;

    /** @var array<string, mixed> */
    protected array $options = [];

    protected ?float $timeout;

    protected bool $blocking = true;

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
        if (\is_resource($this->stream)) {
            stream_set_timeout(
                $this->stream,
                (int) $seconds,
                (int) (($seconds - (int) $seconds) * 1_000_000),
            );
        }

        return $this;
    }

    public function blocking(bool $mode = true): static
    {
        $this->blocking = $mode;
        if (\is_resource($this->stream)) {
            stream_set_blocking($this->stream, $mode);
        }

        return $this;
    }

    public function crypto(?CryptoMethod $method): static
    {
        if (!\is_resource($this->stream)) {
            throw new SocketException('Cannot toggle crypto before connect().');
        }
        if ($method === null) {
            @stream_socket_enable_crypto($this->stream, false);
        } else {
            @stream_socket_enable_crypto($this->stream, true, $method->forClient());
        }

        return $this;
    }

    public function connect(): static
    {
        if (\is_resource($this->stream)) {
            throw new SocketException('Client is already connected.');
        }
        $address = $this->transport->scheme() . '://' . $this->host . ':' . $this->port;
        $errNo = 0;
        $errStr = '';
        $timeout = $this->timeout ?? (float) \ini_get('default_socket_timeout');
        $context = stream_context_create(['ssl' => $this->options]);
        $stream = @stream_socket_client(
            $address,
            $errNo,
            $errStr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if ($stream === false) {
            throw new SocketConnectionException(
                \sprintf('stream_socket_client failed (%d): %s', $errNo, $errStr !== '' ? $errStr : 'unknown error'),
            );
        }
        stream_set_blocking($stream, $this->blocking);
        if ($this->timeout !== null) {
            stream_set_timeout(
                $stream,
                (int) $this->timeout,
                (int) (($this->timeout - (int) $this->timeout) * 1_000_000),
            );
        }
        $this->stream = $stream;

        return $this;
    }

    public function disconnect(): bool
    {
        if (!\is_resource($this->stream)) {
            $this->stream = null;

            return true;
        }
        @fclose($this->stream);
        $this->stream = null;

        return true;
    }

    public function read(int $length = 1024): ?string
    {
        if ($length < 1 || !\is_resource($this->stream) || feof($this->stream)) {
            return null;
        }
        $bytes = @fread($this->stream, $length);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return $bytes;
    }

    public function write(string $data): ?int
    {
        if (!\is_resource($this->stream)) {
            return null;
        }
        $written = @fwrite($this->stream, $data, \strlen($data));

        return $written === false ? null : $written;
    }

    public function getSocket(): mixed
    {
        return $this->stream;
    }
}
