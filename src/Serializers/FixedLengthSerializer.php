<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Provides fixed-length output with 16-characters alphabet.
 */
class FixedLengthSerializer implements SerializerContract
{
    /**
     * 16 chars, shuffled alphabet with removed common vowels and digits to lower probability of building random words.
     */
    public const ALPHABET = 'QFCgDRwkBxMLGKPd';

    private int $maxLength = 16;

    /**
     * @param string $alphabet shuffled alphabet with removed common vowels and digits to lower probability of building random words
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(private string $alphabet = self::ALPHABET)
    {
        $alphabetLength = \strlen($this->alphabet);

        if ($alphabetLength !== 16) {
            throw new \InvalidArgumentException('Invalid alphabet length, must be 16');
        }

        if (preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        if (\extension_loaded('mbstring') && \mb_strlen($this->alphabet) !== $alphabetLength) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }
    }

    public function serialize(array $data): string
    {
        $output = '';

        if ($data[0] > 0xFFFF || $data[1] > 0xFFFF || $data[2] > 0xFFFF || $data[3] > 0xFFFF) {
            throw new IdEncodeException('Out of range value for serializer');
        }

        for ($i = 0; $i < 4; $i++) { // 4 x 16 bits
            for ($b = 12; $b >= 0; $b -= 4) { // each 4 bits in word
                $output .= $this->alphabet[($data[$i] >> $b) & 15];
            }
        }

        return $output;
    }

    public function deserialize(string $data): array
    {
        if ($data === '' || \strlen($data) > $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        $number = 0;

        for ($i = 0; $i < \strlen($data); $i++) {
            $index = \strpos($this->alphabet, $data[$i]);
            if ($index === false) {
                throw new IdDecodeException('Id has invalid characters');
            }

            $number = ($number << 4) | $index;
        }

        $mask = 65535;

        return [
            ($number >> 48) & $mask,
            ($number >> 32) & $mask,
            ($number >> 16) & $mask,
            $number & $mask,
        ];
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxLength;
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }

    public function isConstantLength(): bool
    {
        return true;
    }
}
