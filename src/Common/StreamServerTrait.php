<?php
/**
 * StreamServerTrait.php
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

use InitPHP\Socket\Exception\{SocketConnectionException, SocketException, SocketInvalidArgumentException};

use const STREAM_CRYPTO_METHOD_SSLv2_SERVER;
use const STREAM_CRYPTO_METHOD_SSLv3_SERVER;
use const STREAM_CRYPTO_METHOD_SSLv23_SERVER;
use const STREAM_CRYPTO_METHOD_ANY_SERVER;
use const STREAM_CRYPTO_METHOD_TLS_SERVER;
use const STREAM_CRYPTO_METHOD_TLSv1_0_SERVER;
use const STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;
use const STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;

use function is_float;
use function stream_socket_server;
use function stream_context_create;
use function ini_get;
use function stream_socket_accept;
use function fclose;
use function fread;
use function fwrite;
use function strlen;
use function stream_set_timeout;
use function stream_set_blocking;
use function stream_socket_enable_crypto;
use function strtolower;
use function implode;
use function array_keys;

trait StreamServerTrait
{
    protected ?float $timeout = null;

    protected array $options = [];


    /** @var resource */
    protected $accept;

    public function __construct(string $host, int $port, $argument)
    {
        $this->setHost($host)->setPort($port);
        if($argument !== null && !is_float($argument)){
            throw new SocketInvalidArgumentException('For SSL and TLS servers, the argument must be a float specifying the timeout.');
        }
        $this->timeout = $argument;
    }


    public function connection(): self
    {
        $address = $this->type . '://' . $this->getHost() . ':' . $this->getPort();
        $socket = stream_socket_server($address, $errNo, $errStr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, stream_context_create(['ssl' => $this->options]));
        if($socket === FALSE || !empty($errStr)){
            throw new SocketConnectionException('Connection Error : ' . $errStr);
        }
        $timeout = empty($this->timeout) ? (int)ini_get('default_socket_timeout') : $this->timeout;

        if(($accept = stream_socket_accept($socket, $timeout)) === FALSE){
            throw new SocketConnectionException('Connection Error : ' . $errStr);
        }
        $this->socket = $socket;
        $this->accept = $accept;
        return $this;
    }

    public function disconnect(): bool
    {
        if(isset($this->socket)){
            fclose($this->socket);
        }
        if(isset($this->accept)){
            fclose($this->accept);
        }
        return true;
    }

    public function read(int $length = 1024): ?string
    {
        $read = fread($this->getSocket(), $length);
        return $read === FALSE ? null : $read;
    }

    public function write(string $string): ?int
    {
        $write = fwrite($this->getSocket(), $string, strlen($string));
        return $write === FALSE ? null : $write;
    }

    public function timeout(int $second): self
    {
        stream_set_timeout($this->accept, $second);
        return $this;
    }

    public function blocking(bool $mode = true): self
    {
        stream_set_blocking($this->accept, $mode);
        return $this;
    }

    public function crypto(?string $method = null): self
    {
        if(empty($method)){
            stream_socket_enable_crypto($this->accept, false);
            return $this;
        }
        $method = strtolower($method);
        $algos = [
            'sslv2'   => STREAM_CRYPTO_METHOD_SSLv2_SERVER,
            'sslv3'   => STREAM_CRYPTO_METHOD_SSLv3_SERVER,
            'sslv23'  => STREAM_CRYPTO_METHOD_SSLv23_SERVER,
            'any'     => STREAM_CRYPTO_METHOD_ANY_SERVER,
            'tls'     => STREAM_CRYPTO_METHOD_TLS_SERVER,
            'tlsv1.0' => STREAM_CRYPTO_METHOD_TLSv1_0_SERVER,
            'tlsv1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_SERVER,
            'tlsv1.2' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER
        ];
        if(!isset($algos[$method])){
            throw new SocketException('Unsupported crypto method. This library supports: ' . implode(', ', array_keys($algos)));
        }
        stream_socket_enable_crypto($this->accept, true, $algos[$method]);
        return $this;
    }


    /**
     * @link https://www.php.net/manual/tr/context.ssl.php
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function option(string $key, $value): self
    {
        $this->options[$key] = $value;
        return $this;
    }

}
