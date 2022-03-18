<?php
/**
 * StreamClientTrait.php
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

use const STREAM_CRYPTO_METHOD_SSLv2_CLIENT;
use const STREAM_CRYPTO_METHOD_SSLv3_CLIENT;
use const STREAM_CRYPTO_METHOD_SSLv23_CLIENT;
use const STREAM_CRYPTO_METHOD_ANY_CLIENT;
use const STREAM_CRYPTO_METHOD_TLS_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

use function is_float;
use function stream_socket_client;
use function stream_context_create;
use function fclose;
use function fread;
use function fwrite;
use function stream_set_timeout;
use function stream_set_blocking;
use function stream_socket_enable_crypto;
use function strtolower;
use function implode;
use function array_keys;

trait StreamClientTrait
{
    protected ?float $timeout = null;

    protected array $options = [];

    public function __construct(string $host, int $port, $argument)
    {
        $this->setHost($host)->setPort($port);
        if($argument !== null && !is_float($argument)){
            throw new SocketInvalidArgumentException('For SSL and TLS clients, the argument must be a float specifying the timeout.');
        }
        $this->timeout = $argument;
    }

    public function connection(): self
    {
        $address = $this->type . '://' . $this->getHost() . ':' . $this->getPort();
        $socket = stream_socket_client($address, $errNo, $errStr, $this->timeout, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $this->options]));
        if($socket === FALSE || !empty($errStr)){
            throw new SocketConnectionException('Socket Connection Error : ' . $errStr);
        }
        $this->socket = $socket;
        return $this;
    }

    public function disconnect(): bool
    {
        if(isset($this->socket)){
            return (bool)fclose($this->socket);
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
        stream_set_timeout($this->getSocket(), $second);
        return $this;
    }

    public function blocking(bool $mode = true): self
    {
        stream_set_blocking($this->getSocket(), $mode);
        return $this;
    }

    public function crypto(?string $method = null): self
    {
        if(empty($method)){
            stream_socket_enable_crypto($this->getSocket(), false);
            return $this;
        }
        $method = strtolower($method);
        $algos = [
            'sslv2'   => STREAM_CRYPTO_METHOD_SSLv2_CLIENT,
            'sslv3'   => STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
            'sslv23'  => STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
            'any'     => STREAM_CRYPTO_METHOD_ANY_CLIENT,
            'tls'     => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            'tlsv1.0' => STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT,
            'tlsv1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT,
            'tlsv1.2' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ];
        if(!isset($algos[$method])){
            throw new SocketException('Unsupported crypto method. This library supports: ' . implode(', ', array_keys($algos)));
        }
        stream_socket_enable_crypto($this->getSocket(), true, $algos[$method]);
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
