<?php

declare(strict_types=1);

namespace InitPHP\Socket\Exception;

use InvalidArgumentException;

class SocketInvalidArgumentException extends InvalidArgumentException implements SocketExceptionInterface
{
}
