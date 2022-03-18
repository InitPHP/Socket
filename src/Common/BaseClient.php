<?php
/**
 * BaseClient.php
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

use InitPHP\Socket\Interfaces\SocketClientInterface;

abstract class BaseClient implements SocketClientInterface
{
    use BaseCommon;

    abstract public function connection(): SocketClientInterface;

    abstract public function disconnect(): bool;

    abstract public function read(int $length = 1024): ?string;

    abstract public function write(string $string): ?int;

}
