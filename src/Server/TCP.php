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

use InitPHP\Socket\Socket;
use InitPHP\Socket\Common\{ServerTrait, BaseServer};
use InitPHP\Socket\Exception\{SocketException, SocketListenException};
use \InitPHP\Socket\Interfaces\SocketServerInterface;

use const PHP_BINARY_READ;

use function socket_listen;
use function socket_accept;
use function socket_close;

class TCP extends BaseServer implements SocketServerInterface
{

    use ServerTrait;

    protected int $backlog = 3;

    public function connection(): self
    {
        $this->socket = $this->createSocketSource('tcp', SOCK_STREAM, $this->domain);
        $host = $this->getHost();
        $port = $this->getPort();
        $this->socketBind($this->socket, $host, $port);
        if(socket_listen($this->socket, $this->backlog) === FALSE){
            throw new SocketListenException('Socket Listen Error : ' . $this->getLastError());
        }
        if(($accept = socket_accept($this->socket)) === FALSE){
            throw new SocketException('Socket Accept Error : ' . $this->getLastError());
        }

        $this->clients[] = (new ServerClient([
            'type'          => Socket::TCP,
        ]))->__setSocket($accept);

        return $this;
    }

    public function disconnect(): bool
    {
        if (!empty($this->clients)) {
            foreach ($this->clients as $client) {
                $client->close();
            }
        }

        if(isset($this->socket)){
            socket_close($this->socket);
        }

        return true;
    }

    public function backlog(int $backlog): self
    {
        $this->backlog = $backlog;
        return $this;
    }

}
