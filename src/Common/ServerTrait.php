<?php
/**
 * ServerTrait.php
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

use InitPHP\Socket\Exception\SocketInvalidArgumentException;

use function is_string;

trait ServerTrait
{

    protected ?string $domain;

    public function __construct($host, $port, $argument)
    {
        $this->setHost($host)->setPort($port);
        if($argument !== null && !is_string($argument)){
            throw new SocketInvalidArgumentException('For UDP and TCP servers, the argument must be a string specifying the domain. Only "v4", "v6" or "unix"');
        }
        $this->domain = $argument;
    }

}
