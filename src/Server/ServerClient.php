<?php

namespace InitPHP\Socket\Server;

use \InvalidArgumentException;
use InitPHP\Socket\Interfaces\SocketServerClientInterface;
use InitPHP\Socket\Socket;

use function fread;
use function fclose;
use function socket_close;
use function array_merge;
use function socket_set_nonblock;
use function socket_read;
use function socket_recvfrom;
use function socket_write;
use function socket_sendto;
use function strlen;

class ServerClient implements SocketServerClientInterface
{

    /** @var string|null $id */
    private $id;

    private static array $credentials = [
        'type'          => null,
    ];

    private $socket;

    public function __construct(array $credentials = [])
    {
        !empty($credentials) && self::$credentials = array_merge(self::$credentials, $credentials);
    }

    public function __destruct()
    {
        $this->close();
    }

    public static function __setCallbacks(string $callback, array $arguments): void
    {
        if (!isset(self::$credentials['callbacks'])) {
            self::$credentials['callbacks'] = [];
        }

        self::$credentials['callbacks'][$callback] = $arguments;
    }

    public static function __removeCallbacks(string $callback): void
    {
        if (isset(self::$credentials['callbacks'][$callback])) {
            unset(self::$credentials['callbacks'][$callback]);
        }
    }

    public function __setSocket($socket): self
    {
        if (isset($this->socket)) {
            throw new \Exception("Client cannot be changed");
        }
        $this->socket = $socket;
        socket_set_nonblock($this->socket);

        if (!empty(self::$credentials['callbacks'])) {
            foreach (self::$credentials['callbacks'] as $callback => $arguments) {
                foreach ($arguments as &$argument) {
                    $argument == '{socket}' && $argument = $this->socket;
                }
                call_user_func_array($callback, $arguments);
            }
        }

        echo "New client connected." . \PHP_EOL;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setId($id): self
    {
        if (!is_numeric($id) && !is_string($id)) {
            throw new InvalidArgumentException("The Client ID can be string or numeric.");
        }
        $this->id = $id;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->id ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getSocket()
    {
        return $this->socket ?? false;
    }

    /**
     * @inheritDoc
     */
    public function push(string $message)
    {
        if (!isset($this->socket)) {
            return false;
        }
        switch (self::$credentials['type']) {
            case Socket::TCP:
                return socket_write($this->socket, $message, strlen($message));
            case Socket::UDP:
                return socket_sendto($this->socket, $message, strlen($message), 0, self::$credentials['host'], self::$credentials['port']);
            case Socket::SSL:
            case Socket::TLS:
                return fwrite($this->socket, $message, strlen($message));
            default:
                return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function read(int $length = 1024, ?int $type = null)
    {
        switch (self::$credentials['type']) {
            case Socket::TCP:
                null === $type && $type = \PHP_BINARY_READ;
                return socket_read($this->socket, $length, $type);
            case Socket::UDP:
                $content = null;
                $name = self::$credentials['host'];
                $port = self::$credentials['port'];
                null === $type && $type = 0;
                if (!socket_recvfrom($this->socket, $content, $length, $type, $name, $port)) {
                    return false;
                }
                return null === $content ? false : $content;
            case Socket::SSL:
            case Socket::TLS:
                return fread($this->socket, $length);
            default:
                return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isDisconnected(): bool
    {
        try {
            return !isset($this->socket) || $this->read(1024, \PHP_NORMAL_READ) === false;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        if (!isset($this->socket)) {
            return true;
        }

        switch (self::$credentials['type']) {
            case Socket::TCP:
            case Socket::UDP:
                socket_close($this->socket);
                break;
            case Socket::TLS:
            case Socket::SSL:
                fclose($this->socket);
                break;
        }

        unset($this->socket);

        echo "Client disconnected." . \PHP_EOL;

        return true;
    }

}
