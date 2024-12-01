<?php

namespace InitPHP\Socket\Interfaces;

interface SocketServerClientInterface
{

    /**
     * @param int|string $id
     * @return self
     */
    public function setId($id): self;

    /**
     * @return string|int|null
     */
    public function getId();

    /**
     * @param string $message
     * @return int|false
     */
    public function push(string $message);

    /**
     * @param int $length
     * @param int|null $type
     * @return string|false
     */
    public function read(int $length = 1024, ?int $type = null);

    /**
     * @return bool
     */
    public function close(): bool;

    /**
     * @return false|resource|\Socket
     */
    public function getSocket();

    /**
     * @return bool
     */
    public function isDisconnected(): bool;

}
