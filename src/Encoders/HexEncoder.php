<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encoders;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Provides fixed-length hexadecimal output.
 * Fastest from encoders but not customizable.
 */
class HexEncoder implements EncoderContract
{
    private int $maxEncodedLength = 16;

    public function encode(int $id): string
    {
        if ($id < 0) {
            throw new IdEncodeException('Encoded ID must be positive.');
        }

        return \dechex($id);
    }

    public function decode(string $id): int
    {
        if (\strlen($id) === 0 || \strlen($id) > $this->maxEncodedLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        if ((int) \preg_match('/^[0123456789abcdef]+$/', $id) === 0) {
            throw new IdDecodeException('Id has invalid characters');
        }

        // hexdec returns '0' if sth goes wrong, silent warning and catch
        $number = @\hexdec($id);

        if (\is_float($number) || ($number === 0 && $id !== '0')) { // @phpstan-ignore function.impossibleType (this can be float type when overflowed)
            throw new IdDecodeException('Decoded ID is invalid');
        }

        return $number;
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxEncodedLength;
    }

    public function getAlphabet(): string
    {
        return '0123456789abcdef';
    }

    public function isConstantLength(): bool
    {
        return false;
    }
}
