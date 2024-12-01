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

use InitPHP\Socket\Server\ServerClient;
use InitPHP\Socket\Socket;
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

        $this->clients[] = (new ServerClient([
            'type'          => $this->type === 'tls' ? Socket::TLS : Socket::SSL,
            'host'          => $this->getHost(),
            'port'          => $this->getPort(),
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

        if(!empty($this->socket)){
            fclose($this->socket);
        }

        return true;
    }

    public function timeout(int $second): self
    {
        if (!empty($this->clients)) {
            foreach ($this->clients as $client) {
                stream_set_timeout($client->getSocket(), $second);
            }
            ServerClient::__setCallbacks('stream_set_timeout', ['{socket}', $second]);
        }

        return $this;
    }

    public function blocking(bool $mode = true): self
    {
        if (!empty($this->clients)) {
            foreach ($this->clients as $client) {
                stream_set_blocking($client->getSocket(), $mode);
            }
            ServerClient::__setCallbacks('stream_set_blocking', ['{socket}', $mode]);
        }


        return $this;
    }

    public function crypto(?string $method = null): self
    {
        if(empty($method)){
            if (!empty($this->clients)) {
                foreach ($this->clients as $client) {
                    stream_socket_enable_crypto($client->getSocket(), false);
                }
                ServerClient::__setCallbacks('stream_socket_enable_crypto', ['{socket}', false]);
            }
            
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

        if (!empty($this->clients)) {
            foreach ($this->clients as $client) {
                stream_socket_enable_crypto($client->getSocket(), true, $algos[$method]);
            }
            ServerClient::__setCallbacks('stream_socket_enable_crypto', ['{socket}', true, $algos[$method]]);
        }

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
