<?php

declare(strict_types=1);

namespace InitPHP\Socket\Client;

use InitPHP\Socket\Enum\Transport;

final class SSL extends AbstractStreamClient
{
    public function __construct(string $host, int $port, ?float $timeout = null)
    {
        parent::__construct($host, $port, Transport::SSL, $timeout);
    }
}
