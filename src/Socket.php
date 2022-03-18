<?php
/**
 * Socket.php
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

namespace InitPHP\Socket;

use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use InitPHP\Socket\Interfaces\{SocketClientInterface, SocketServerInterface};

class Socket
{

    public const SSL = 1;
    public const TCP = 2;
    public const TLS = 3;
    public const UDP = 4;

    /**
     * @param int $handler
     * @param string $host
     * @param int $port
     * @param null|string|float $argument <p>This value is the value that will be sent as 3 parameters to the constructor method of the handler.
     * SSL or TLS = (float) Defines the timeout period.
     * UDP or TCP = (string) Defines the protocol family to be used by the socket. "v4", "v6" or "unix"
     * </p>
     * @return SocketServerInterface
     */
    public static function server(int $handler = self::TCP, string $host = '', int $port = 0, $argument = null): SocketServerInterface
    {
        if(empty($host) || empty($port)){
            throw new SocketInvalidArgumentException('Server: host and port must be specified.');
        }
        switch ($handler) {
            case self::SSL:
                return new \InitPHP\Socket\Server\SSL($host, $port, $argument);
            case self::TCP:
                return new \InitPHP\Socket\Server\TCP($host, $port, $argument);
            case self::TLS:
                return new \InitPHP\Socket\Server\TLS($host, $port, $argument);
            case self::UDP:
                return new \InitPHP\Socket\Server\UDP($host, $port, $argument);
            default:
                throw new SocketInvalidArgumentException("\$handler can only be one of the constants \"Socket::SSL\", \"Socket::TCP\", \"Socket::TLS\" or \"Socket::UDP\" .");
        }
    }

    /**
     * @param int $handler
     * @param string $host
     * @param int $port
     * @param null|string|float $argument <p>This value is the value that will be sent as 3 parameters to the constructor method of the handler.
     * SSL or TLS = (float) Defines the timeout period.
     * UDP or TCP = (string) Defines the protocol family to be used by the socket. "v4", "v6" or "unix"
     * </p>
     * @return SocketClientInterface
     */
    public static function client(int $handler = self::TCP, string $host = '', int $port = 0, $argument = null): SocketClientInterface
    {
        if(empty($host) || empty($port)){
            throw new SocketInvalidArgumentException('Client: host and port must be specified.');
        }
        switch ($handler) {
            case self::SSL:
                return new \InitPHP\Socket\Client\SSL($host, $port, $argument);
            case self::TCP:
                return new \InitPHP\Socket\Client\TCP($host, $port, $argument);
            case self::TLS:
                return new \InitPHP\Socket\Client\TLS($host, $port, $argument);
            case self::UDP:
                return new \InitPHP\Socket\Client\UDP($host, $port, $argument);
            default:
                throw new SocketInvalidArgumentException("\$handler can only be one of the constants \"Socket::SSL\", \"Socket::TCP\", \"Socket::TLS\" or \"Socket::UDP\" .");
        }
    }

}
