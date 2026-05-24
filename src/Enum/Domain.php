<?php

declare(strict_types=1);

namespace InitPHP\Socket\Enum;

use InitPHP\Socket\Exception\SocketInvalidArgumentException;

use const AF_INET;
use const AF_INET6;
use const AF_UNIX;

enum Domain: string
{
    case V4 = 'v4';
    case V6 = 'v6';
    case UNIX = 'unix';

    public function toAddressFamily(): int
    {
        return match ($this) {
            self::V4 => AF_INET,
            self::V6 => AF_INET6,
            self::UNIX => AF_UNIX,
        };
    }

    public static function fromName(?string $name): self
    {
        if ($name === null || $name === '') {
            return self::V4;
        }
        $value = strtolower($name);
        $case = self::tryFrom($value);
        if ($case === null) {
            throw new SocketInvalidArgumentException(
                \sprintf('Unknown domain "%s". Expected one of: v4, v6, unix.', $name),
            );
        }

        return $case;
    }
}
