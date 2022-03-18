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

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Common\{BaseServer, StreamServerTrait};
use InitPHP\Socket\Interfaces\SocketServerInterface;

class SSL extends BaseServer implements SocketServerInterface
{

    use StreamServerTrait;

    protected string $type = 'ssl';

}
