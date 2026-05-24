<?php

declare(strict_types=1);

namespace InitPHP\Socket\Enum;

enum Transport: string
{
    case TCP = 'tcp';
    case UDP = 'udp';
    case TLS = 'tls';
    case SSL = 'ssl';

    public function isStream(): bool
    {
        return $this === self::TLS || $this === self::SSL;
    }

    public function isDatagram(): bool
    {
        return $this === self::UDP;
    }

    public function scheme(): string
    {
        return $this->value;
    }
}
