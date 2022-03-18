<?php
/**
 * BaseServer.php
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

use InitPHP\Socket\Interfaces\SocketServerInterface;

use function sleep;

abstract class BaseServer implements SocketServerInterface
{
    use BaseCommon;

    abstract public function connection(): SocketServerInterface;

    abstract public function disconnect(): bool;

    abstract public function read(int $length = 1024): ?string;

    abstract public function write(string $string): ?int;

    public function live(callable $callback): void
    {
        while (true) {
            $callback($this);
        }
    }

    public function wait(int $second): void
    {
        sleep($second);
    }

}
