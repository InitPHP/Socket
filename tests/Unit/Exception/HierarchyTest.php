<?php

declare(strict_types=1);

namespace InitPHP\Socket\Tests\Unit\Exception;

use InitPHP\Socket\Exception\SocketConnectionException;
use InitPHP\Socket\Exception\SocketException;
use InitPHP\Socket\Exception\SocketExceptionInterface;
use InitPHP\Socket\Exception\SocketInvalidArgumentException;
use InitPHP\Socket\Exception\SocketListenException;
use PHPUnit\Framework\TestCase;

final class HierarchyTest extends TestCase
{
    public function testAllExceptionsShareTheSocketExceptionInterface(): void
    {
        self::assertInstanceOf(SocketExceptionInterface::class, new SocketException('x'));
        self::assertInstanceOf(SocketExceptionInterface::class, new SocketConnectionException('x'));
        self::assertInstanceOf(SocketExceptionInterface::class, new SocketListenException('x'));
        self::assertInstanceOf(SocketExceptionInterface::class, new SocketInvalidArgumentException('x'));
    }

    public function testSpecificExceptionsExtendSocketException(): void
    {
        self::assertInstanceOf(SocketException::class, new SocketConnectionException('x'));
        self::assertInstanceOf(SocketException::class, new SocketListenException('x'));
    }

    public function testInvalidArgumentRemainsAnInvalidArgumentException(): void
    {
        self::assertInstanceOf(\InvalidArgumentException::class, new SocketInvalidArgumentException('x'));
    }
}
