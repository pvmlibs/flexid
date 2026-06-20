<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * Serializes any number (positive and negative), supports only alphabet with power of 2 length.
 * It doesn't require any php extensions. Faster than CustomSerializer.
 * This is not intended for security. For confidentiality use encryption.
 */
class BaseSerializer implements SerializerContract
{
    private readonly int $alphabetLength;
    private readonly int $alphabetBits;

    private readonly int $maxLength;

    private readonly int $maxPosition;

    /**
     * 32 chars, shuffled alphabet with removed common vowels and digits to lower probability of building random words.
     * Max 13 chars output length.
     */
    public const ALPHABET = 'FDxkwdMKQRGgCBPLpYmvVJyXZbjczWqf';

    /**
     * Shuffled 64 chars, a-zA-Z0-9-_. This can create random words, max 11 chars output length.
     */
    public const EXTENDED_ALPHABET = 'R9jHJSAcLBfFroKqTzXvgxtNbsp83DEkVMIn-y0m5hdG_P47awQl26uYZUeW1iCO';

    /**
     * @param string $alphabet Length must be power of 2
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private string $alphabet = self::ALPHABET,
    ) {
        $this->alphabetLength = \strlen($this->alphabet);

        switch ($this->alphabetLength) {
            case 64: $this->alphabetBits = 6;
                $this->maxPosition = 15;
                $this->maxLength = 11;
                break;
            case 32: $this->alphabetBits = 5;
                $this->maxPosition = 15;
                $this->maxLength = 13;
                break;
            case 16: $this->alphabetBits = 4;
                $this->maxPosition = 15;
                $this->maxLength = 16;
                break;
            case 8: $this->alphabetBits = 3;
                $this->maxPosition = 1;
                $this->maxLength = 22;
                break;
            case 4: $this->alphabetBits = 2;
                $this->maxPosition = 3;
                $this->maxLength = 32;
                break;
            case 2: $this->alphabetBits = 1;
                $this->maxPosition = 1;
                $this->maxLength = 64;
                break;
            default:
                throw new \InvalidArgumentException('Invalid alphabet length, must be power of 2 and <= 64, got ' . $this->alphabetLength);
        }

        if (\preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        if (\extension_loaded('mbstring') && mb_strlen($this->alphabet) !== $this->alphabetLength) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }
    }

    public function serialize(int $data): string
    {
        $mask = (1 << $this->alphabetBits) - 1;

        $reminder = $data & $mask;
        if ($data < 0) {
            // temporary reset sign bit, shift data and set again this bit shifted to get positive number
            $data &= ~(1 << 63);
            $quotient = ($data >> $this->alphabetBits);
            $quotient |= (1 << (63 - $this->alphabetBits));
        } else {
            $quotient = ($data >> $this->alphabetBits);
        }

        $result = $this->alphabet[$reminder];

        while ($quotient > 0) {
            $reminder = $quotient & $mask;
            $quotient >>= $this->alphabetBits;
            $result .= $this->alphabet[$reminder];
        }

        return $result;
    }

    public function deserialize(string $data): int
    {
        if ($data === '' || \strlen($data) > $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        $index = \strpos($this->alphabet, $data[\strlen($data) - 1]);
        if ($index === false) {
            throw new IdDecodeException('Id has invalid characters');
        }

        if (\strlen($data) === $this->maxLength && $index > $this->maxPosition) {
            throw new IdDecodeException('Id is out of range');
        }

        $number = $index;

        for ($i = \strlen($data) - 2; $i >= 0; $i--) {
            $index = \strpos($this->alphabet, $data[$i]);
            if ($index === false) {
                throw new IdDecodeException('Id has invalid characters');
            }
            $number = ($number << $this->alphabetBits) + $index;
        }

        return $number;
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxLength;
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }
}
