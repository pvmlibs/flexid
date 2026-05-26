<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encoders;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Provides fixed-length output with 16-characters alphabet.
 */
class FixedLengthEncoder implements EncoderContract
{
    /**
     * 16 chars, shuffled alphabet with removed common vowels and digits to lower probability of building random words.
     */
    public const ALPHABET = 'QFCgDRwkBxMLGKPd';

    private int $maxEncodedLength = 16;

    /**
     * @param string $alphabet it's recommended to use shuffled version of default alphabet or use your own
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly string $alphabet = self::ALPHABET,
    ) {
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

    public function encode(int $id): string
    {
        if ($id < 0) {
            throw new IdEncodeException('Encoded ID must be positive.');
        }

        $output = '';
        for ($i = 60; $i >= 0; $i -= 4) {
            $output .= \substr($this->alphabet, ($id >> $i) & 15, 1);
        }

        return $output;
    }

    public function decode(string $id): int
    {
        if (\strlen($id) === 0 || \strlen($id) > $this->maxEncodedLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        $number = 0;
        for ($i = 0; $i < \strlen($id); $i++) {
            $index = \strpos($this->alphabet, $id[$i]);
            if ($index === false) {
                throw new IdDecodeException('Id has invalid characters');
            }
            $number = ($number << 4) | $index;
        }

        if ($number < 0 || \is_float($number)) { // @phpstan-ignore function.impossibleType (this can be float type when overflowed)
            throw new IdDecodeException('Decoded ID is out of valid range');
        }

        return $number;
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxEncodedLength;
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
