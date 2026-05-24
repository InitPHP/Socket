<?php

declare(strict_types=1);

namespace InitPHP\Socket\Enum;

use InitPHP\Socket\Exception\SocketInvalidArgumentException;

use const STREAM_CRYPTO_METHOD_ANY_CLIENT;
use const STREAM_CRYPTO_METHOD_ANY_SERVER;
use const STREAM_CRYPTO_METHOD_SSLv23_CLIENT;
use const STREAM_CRYPTO_METHOD_SSLv23_SERVER;
use const STREAM_CRYPTO_METHOD_SSLv2_CLIENT;
use const STREAM_CRYPTO_METHOD_SSLv2_SERVER;
use const STREAM_CRYPTO_METHOD_SSLv3_CLIENT;
use const STREAM_CRYPTO_METHOD_SSLv3_SERVER;
use const STREAM_CRYPTO_METHOD_TLS_CLIENT;
use const STREAM_CRYPTO_METHOD_TLS_SERVER;
use const STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_0_SERVER;
use const STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;
use const STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;

enum CryptoMethod: string
{
    case SSLv2 = 'sslv2';
    case SSLv3 = 'sslv3';
    case SSLv23 = 'sslv23';
    case ANY = 'any';
    case TLS = 'tls';
    case TLSv1_0 = 'tlsv1.0';
    case TLSv1_1 = 'tlsv1.1';
    case TLSv1_2 = 'tlsv1.2';

    public function forClient(): int
    {
        return match ($this) {
            self::SSLv2 => STREAM_CRYPTO_METHOD_SSLv2_CLIENT,
            self::SSLv3 => STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
            self::SSLv23 => STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
            self::ANY => STREAM_CRYPTO_METHOD_ANY_CLIENT,
            self::TLS => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            self::TLSv1_0 => STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT,
            self::TLSv1_1 => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT,
            self::TLSv1_2 => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        };
    }

    public function forServer(): int
    {
        return match ($this) {
            self::SSLv2 => STREAM_CRYPTO_METHOD_SSLv2_SERVER,
            self::SSLv3 => STREAM_CRYPTO_METHOD_SSLv3_SERVER,
            self::SSLv23 => STREAM_CRYPTO_METHOD_SSLv23_SERVER,
            self::ANY => STREAM_CRYPTO_METHOD_ANY_SERVER,
            self::TLS => STREAM_CRYPTO_METHOD_TLS_SERVER,
            self::TLSv1_0 => STREAM_CRYPTO_METHOD_TLSv1_0_SERVER,
            self::TLSv1_1 => STREAM_CRYPTO_METHOD_TLSv1_1_SERVER,
            self::TLSv1_2 => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER,
        };
    }

    public static function fromName(string $name): self
    {
        $case = self::tryFrom(strtolower($name));
        if ($case === null) {
            throw new SocketInvalidArgumentException(\sprintf(
                'Unsupported crypto method "%s". Expected one of: %s.',
                $name,
                implode(', ', array_map(static fn (self $c): string => $c->value, self::cases())),
            ));
        }

        return $case;
    }
}
