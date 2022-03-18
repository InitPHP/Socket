<?php
/**
 * SSL.php
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

namespace InitPHP\Socket\Client;

use \InitPHP\Socket\Common\{StreamClientTrait, BaseClient};
use \InitPHP\Socket\Interfaces\SocketClientInterface;

class SSL extends BaseClient implements SocketClientInterface
{

    use StreamClientTrait;

    protected string $type = 'ssl';

}
