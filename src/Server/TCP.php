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

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Common\{ServerTrait, BaseServer};
use InitPHP\Socket\Exception\{SocketException, SocketListenException};
use \InitPHP\Socket\Interfaces\SocketServerInterface;

use const PHP_BINARY_READ;

use function socket_listen;
use function socket_accept;
use function socket_close;
use function socket_read;
use function socket_write;
use function strlen;

class TCP extends BaseServer implements SocketServerInterface
{

    use ServerTrait;

    /** @var resource */
    protected $accept;

    protected int $backlog = 3;

    public function connection(): self
    {
        $socket = $this->createSocketSource('tcp', SOCK_STREAM, $this->domain);
        $host = $this->getHost();
        $port = $this->getPort();
        $this->socketBind($socket, $host, $port);
        if(socket_listen($socket, $this->backlog) === FALSE){
            throw new SocketListenException('Socket Listen Error : ' . $this->getLastError());
        }
        if(($accept = socket_accept($socket)) === FALSE){
            throw new SocketException('Socket Accept Error : ' . $this->getLastError());
        }
        $this->socket = $socket;
        $this->accept = $accept;
        return $this;
    }

    public function disconnect(): bool
    {
        if(isset($this->accept)){
            socket_close($this->accept);
        }
        if(isset($this->socket)){
            socket_close($this->socket);
        }
        return true;
    }

    public function read(int $length = 1024, int $type = PHP_BINARY_READ): ?string
    {
        $read = socket_read($this->accept, $length, $type);
        return $read === FALSE ? null : $read;
    }

    public function write(string $string): ?int
    {
        $write = socket_write($this->accept, $string, strlen($string));
        return $write === FALSE ? null : $write;
    }

    public function backlog(int $backlog): self
    {
        $this->backlog = $backlog;
        return $this;
    }

}
