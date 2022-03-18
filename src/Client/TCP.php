<?php
/**
 * TCP.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Socket\Client;

use \InitPHP\Socket\Exception\{SocketConnectionException, SocketInvalidArgumentException};
use \InitPHP\Socket\Common\BaseClient;
use \InitPHP\Socket\Interfaces\SocketClientInterface;

use const PHP_BINARY_READ;
use const SOCK_STREAM;

use function is_string;
use function socket_connect;
use function socket_close;
use function socket_read;
use function socket_write;
use function strlen;

class TCP extends BaseClient implements SocketClientInterface
{

    protected ?string $domain;

    /**
     * @param string $host
     * @param int $port
     * @param $argument <p>domain</p>
     */
    public function __construct(string $host, int $port, $argument)
    {
        $this->setHost($host)->setPort($port);
        if($argument !== null && !is_string($argument)){
            throw new SocketInvalidArgumentException('The TCP client must have a value pointing to the argument domain. Only "v4", "v6" or "unix"');
        }
        $this->domain = $argument;
    }

    public function connection(): self
    {
        $socket = $this->createSocketSource('tcp', SOCK_STREAM, $this->domain);
        if(socket_connect($socket, $this->getHost(), $this->getPort()) === FALSE){
            throw new SocketConnectionException('Socket Connection Error : ' . $this->getLastError());
        }
        $this->socket = $socket;
        return $this;
    }

    public function disconnect(): bool
    {
        if(isset($this->socket)){
            socket_close($this->socket);
        }
        return true;
    }

    public function read(int $length = 1024, int $type = PHP_BINARY_READ): ?string
    {
        $read = socket_read($this->getSocket(), $length, $type);
        return $read === FALSE ? null : $read;
    }

    public function write(string $string): ?int
    {
        $write = socket_write($this->getSocket(), $string, strlen($string));
        return $write === FALSE ? null : $write;
    }

}
