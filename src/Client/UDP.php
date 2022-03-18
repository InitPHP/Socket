<?php
/**
 * UDP.php
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

use const SOCK_DGRAM;

use function is_string;
use function socket_connect;
use function socket_close;
use function socket_recvfrom;
use function socket_sendto;
use function strlen;

class UDP extends BaseClient implements SocketClientInterface
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
            throw new SocketInvalidArgumentException('The UDP client must have a value pointing to the argument domain. Only "v4", "v6" or "unix"');
        }
        $this->domain = $argument;
    }

    public function connection(): self
    {
        $socket = $this->createSocketSource('udp', SOCK_DGRAM, $this->domain);
        $host = $this->getHost();
        $port = $this->getPort();
        if(socket_connect($socket, $host, $port) === FALSE){
            throw new SocketConnectionException('Socket could not be connected. #' . $this->getLastError());
        }
        $this->socket = $socket;
        $this->host = $host;
        $this->port = $port;
        return $this;
    }

    public function disconnect(): bool
    {
        if(isset($this->socket)){
            socket_close($this->socket);
        }
        return true;
    }

    /**
     * @param int $length
     * @param int $type <p>\MSG_OOB, \MSG_PEEK, \MSG_WAITALL or \MSG_DONTWAIT consts</p>
     * @return string|null
     */
    public function read(int $length = 1024, int $type = 0): ?string
    {
        $read = socket_recvfrom($this->getSocket(), $content, $length, $type, $name, $port);
        if($read === FALSE || empty($content)){
            return null;
        }
        return $content;
    }

    /**
     * @param string $string
     * @param int $type <p>\MSG_OOB, \MSG_EOR, \MSG_EOF or \MSG_DONTROUTE consts</p>
     * @return int|null
     */
    public function write(string $string, int $type = 0): ?int
    {
        $write = socket_sendto($this->getSocket(), $string, strlen($string), $type, $this->getHost(), $this->getPort());
        return $write === FALSE ? null : $write;
    }

}
