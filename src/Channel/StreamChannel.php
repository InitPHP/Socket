<?php

declare(strict_types=1);

namespace InitPHP\Socket\Channel;

use InitPHP\Socket\Interfaces\ChannelInterface;

use function fclose;
use function feof;
use function fread;
use function fwrite;

final class StreamChannel implements ChannelInterface
{
    /** @var resource|null */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!\is_resource($stream)) {
            throw new \InvalidArgumentException('StreamChannel expects a stream resource.');
        }
        $this->stream = $stream;
    }

    public function read(int $length = 1024, ?int $flag = null): ?string
    {
        if ($length < 1 || !\is_resource($this->stream)) {
            return null;
        }
        $bytes = @fread($this->stream, $length);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return $bytes;
    }

    public function write(string $data): ?int
    {
        if (!\is_resource($this->stream)) {
            return null;
        }
        $written = @fwrite($this->stream, $data, \strlen($data));

        return $written === false ? null : $written;
    }

    public function close(): bool
    {
        if (!\is_resource($this->stream)) {
            $this->stream = null;

            return true;
        }
        @fclose($this->stream);
        $this->stream = null;

        return true;
    }

    public function isAlive(): bool
    {
        return \is_resource($this->stream) && !feof($this->stream);
    }

    /**
     * @return resource|null
     */
    public function getResource(): mixed
    {
        return $this->stream;
    }
}
