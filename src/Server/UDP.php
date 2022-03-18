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

use function socket_close;
use function socket_recvfrom;
use function socket_sendto;
use function strlen;

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
     * @param int $type <p>MSG_OBB, MSG_PEEK, MSG_WAITALL or MSG_DONTWAIT</p>
     * @return string|null
     * @throws \InitPHP\Socket\Exception\SocketException
     */
    public function read(int $length = 1024, int $type = 0): ?string
    {
        $read = socket_recvfrom($this->getSocket(), $content, $length, $type, $name, $port);
        if($read === FALSE){
            return null;
        }
        return empty($content) ? null : $content;
    }

    public function write(string $string, int $type = 0): ?int
    {
        $write = socket_sendto($this->getSocket(), $string, strlen($string), $type, $this->getHost(), $this->getPort());
        return $write === FALSE ? null : $write;
    }

}
