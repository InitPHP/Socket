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

    public function connection(): SocketServerInterface;

    public function disconnect(): bool;

    public function read(int $length = 1024): ?string;

    public function write(string $string): ?int;

    public function live(callable $callback): void;

    public function wait(int $second): void;

}
