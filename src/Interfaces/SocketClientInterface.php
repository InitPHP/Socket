<?php
/**
 * SocketClientInterface.php
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

interface SocketClientInterface
{

    public function setHost(string $host): SocketClientInterface;

    public function getHost(): string;

    public function setPort(int $port): SocketClientInterface;

    public function getPort(): int;

    /**
     * @return resource
     */
    public function getSocket();

    public function connection(): SocketClientInterface;

    public function disconnect(): bool;

    public function read(int $length = 1024): ?string;

    public function write(string $string): ?int;

}
