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

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Common\{BaseServer, ServerTrait};
use InitPHP\Socket\Interfaces\SocketServerInterface;

use InitPHP\Socket\Socket;
use function socket_close;

class UDP extends BaseServer implements SocketServerInterface
{
    use ServerTrait;

    public function connection(): self
    {
        $socket = $this->createSocketSource('udp', SOCK_DGRAM, $this->domain);
        $host = $this->getHost();
        $port = $this->getPort();
        $this->socketBind($socket, $host, $port);
        $this->socket = $socket;
        $this->host = $host;
        $this->port = $port;

        $this->clients[] = (new ServerClient([
            'type'          => Socket::UDP,
            'host'          => $this->host,
            'port'          => $this->port,
        ]))->__setSocket($socket);

        return $this;
    }

    public function disconnect(): bool
    {
        if (!empty($this->clients)) {
            foreach ($this->clients as $client) {
                $client->close();
            }
        }

        if(!empty($this->socket)){
            socket_close($this->socket);
        }

        return true;
    }

}
