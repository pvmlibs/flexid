<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Provides fixed-length output with 16-characters alphabet.
 * Fastest from serializers but not customizable.
 */
class HexSerializer implements SerializerContract
{
    private int $maxLength = 16;

    public function serialize(array $data): string
    {
        if ($data[0] > 0xFFFF || $data[1] > 0xFFFF || $data[2] > 0xFFFF || $data[3] > 0xFFFF) {
            throw new IdEncodeException('Out of range value for serializer');
        }

        return \bin2hex(\pack('n4', ...$data));
    }

    public function deserialize(string $data): array
    {
        if (\strlen($data) !== $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        // silent warnings, if data is incorrect it will return false and we throw exception
        $number = @hex2bin($data);

        if ($number === false) {
            throw new IdDecodeException('Failed to convert string id to binary format');
        }

        $unpacked = unpack('n4', $number);

        if ($unpacked === false) {
            throw new IdDecodeException('Cannot unpack hex string to 4 unsigned shorts');
        }

        return \array_values($unpacked);
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxLength;
    }

    public function getAlphabet(): string
    {
        return '0123456789abcdef';
    }

    public function isConstantLength(): bool
    {
        return true;
    }
}
