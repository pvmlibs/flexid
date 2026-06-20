<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * Provides url safe, base64 output for any number.
 * Fast but not customizable.
 */
class Base64Serializer implements SerializerContract
{
    private int $maxLength = 11;

    public function serialize(int $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], \base64_encode(\pack('J', $data)));
    }

    public function deserialize(string $data): int
    {
        if (\strlen($data) > $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        // silent warnings, if data is incorrect it will return false and we throw exception
        $number = @\base64_decode(str_replace(['-', '_'], ['+', '/'], $data), true);

        if ($number === false) {
            throw new IdDecodeException('Failed to convert string id to binary format');
        }

        $unpacked = @unpack('J', $number);

        if ($unpacked === false) {
            throw new IdDecodeException('Cannot unpack base64 string');
        }

        return $unpacked[1];
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxLength;
    }

    public function getAlphabet(): string
    {
        return '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';
    }
}
