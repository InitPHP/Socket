<?php
/**
 * BaseCommon.php
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

namespace InitPHP\Socket\Common;

use InitPHP\Socket\Exception\{SocketException, SocketInvalidArgumentException};

use const AF_INET;
use const AF_INET6;
use const AF_UNIX;

use function getprotobyname;
use function socket_create;
use function socket_last_error;
use function socket_bind;

trait BaseCommon
{
    /** @var resource */
    protected $socket;

    protected string $host;
    protected int $port;


    protected array $domains = [
        'v4'    => AF_INET,
        'v6'    => AF_INET6,
        'unix'  => AF_UNIX,
    ];

    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function getHost(): string
    {
        if(!isset($this->host)){
            throw new SocketException('It cannot be used without the "host" being defined.');
        }
        return $this->host;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function getPort(): int
    {
        if(!isset($this->port)){
            throw new SocketException('It cannot be used without a "port" defined.');
        }
        return $this->port;
    }

    /**
     * @inheritDoc
     */
    public function getSocket()
    {
        if(!isset($this->socket)){
            throw new SocketException('The socket cannot be reachable before the connection is made.');
        }

        return $this->socket;
    }


    protected function createSocketSource($protocol, $type, $domain)
    {
        $domain = empty($domain) ? 'v4' : $domain;
        $protocol = getprotobyname($protocol);
        if(!isset($this->domains[$domain])){
            throw new SocketInvalidArgumentException('Socket resource creation failed! Reason: Invalid domain. Only "v4", "v6" or "unix"');
        }
        return socket_create($this->domains[$domain], $type, $protocol);
    }

    protected function getLastError(): int
    {
        return socket_last_error();
    }

    protected function socketBind(&$socket, &$host, &$port)
    {
        if(socket_bind($socket, $host, $port) === FALSE){
            throw new SocketException('SocketBind Error : ' . socket_last_error());
        }
    }

}
