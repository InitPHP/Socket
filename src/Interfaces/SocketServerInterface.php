<?php
/**
 * SocketServerInterface.php
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

namespace InitPHP\Socket\Interfaces;

interface SocketServerInterface
{
    public function setHost(string $host): SocketServerInterface;

    public function getHost(): string;

    public function setPort(int $port): SocketServerInterface;

    public function getPort(): int;

    /**
     * @return resource
     */
    public function getSocket();

    /**
     * @return SocketServerClientInterface[]
     */
    public function getClients(): array;

    /**
     * @return SocketServerInterface
     */
    public function connection(): SocketServerInterface;

    /**
     * @return bool
     */
    public function disconnect(): bool;

    /**
     * @param string $message
     * @return bool
     */
    public function broadcast(string $message): bool;

    /**
     * @param callable $callback
     * @param int $usleep
     * @return void
     */
    public function live(callable $callback, int $usleep = 100000): void;

    /**
     * @param int|float $second
     * @return void
     */
    public function wait($second): void;

}
