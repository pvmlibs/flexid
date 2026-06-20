<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * Provides fixed-length output with 16-characters alphabet.
 * Fast but not customizable.
 */
class HexSerializer implements SerializerContract
{
    private int $maxLength = 16;

    public function serialize(int $data): string
    {
        return \bin2hex(\pack('J', $data));
    }

    public function deserialize(string $data): int
    {
        if (\strlen($data) !== $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        // silent warnings, if data is incorrect it will return false and we throw exception
        $number = @hex2bin($data);

        if ($number === false) {
            throw new IdDecodeException('Failed to convert string id to binary format');
        }

        $unpacked = @unpack('J', $number);

        if ($unpacked === false) {
            throw new IdDecodeException('Cannot unpack hex string to 4 unsigned shorts');
        }

        return $unpacked[1];
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxLength;
    }

    public function getAlphabet(): string
    {
        return '0123456789abcdef';
    }
}
