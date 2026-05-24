<?php

declare(strict_types=1);

namespace InitPHP\Socket\Server;

use InitPHP\Socket\Enum\Transport;

final class SSL extends AbstractStreamServer
{
    public function __construct(string $host, int $port, ?float $timeout = null)
    {
        parent::__construct($host, $port, Transport::SSL, $timeout);
    }
}
