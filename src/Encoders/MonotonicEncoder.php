<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encoders;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Convert integer ID to/from shorter text format through encoding. This is not encryption of any kind. Main purposes:
 *  - provide as short string from int id as possible, given provided alphabet
 *  - probability of building random words should be very low, especially profanity ones, but we also don't want to maintain
 *    custom dictionary with blacklisted words.
 *  - encoded id is monotonic - sequential id are similar when encoded but will not be in order when sorting e.g. in DB
 *  - fast encode/decode
 * If you want id as short as possible and don't care about building random words, you can add more chars to alphabet.
 * Remember that once set, along with offset they can't be changed, or you won't be able to decode id that was encoded
 * with different parameters.
 * This is not intended as secure solution for hiding raw id. It obfuscates id through custom alphabet, is fast and
 * provides shorter id. Set shuffled version of the alphabet (e.g. with str_shuffle() and offset) to get best results.
 *
 * About 15% faster than PseudoRandomEncoder
 */
class MonotonicEncoder implements EncoderContract
{
    /**
     * 32 chars, shuffled alphabet with removed common vowels and digits to lower probability of building random words.
     */
    public const SAFE_ALPHABET = 'FDxkwdMKQRGgCBPLpYmvVJyXZbjczWqf';

    /**
     * Shuffled 64 chars, a-zA-Z0-9-_.
     */
    public const EXTENDED_ALPHABET = 'R9jHJSAcLBfFroKqTzXvgxtNbsp83DEkVMIn-y0m5hdG_P47awQl26uYZUeW1iCO';

    private readonly int $maxEncodedLength;
    private readonly int $alphabetBits;

    /**
     * @param string $alphabet It's recommended to use shuffled version of default alphabet.
     *                         Set once for application lifetime.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly string $alphabet = self::SAFE_ALPHABET,
    ) {
        $alphabetLength = \strlen($this->alphabet);

        if ($alphabetLength < 2 || ($alphabetLength & ($alphabetLength - 1)) !== 0) {
            throw new \InvalidArgumentException('Invalid alphabet length, must be power of 2, got ' . $alphabetLength);
        }

        if (preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        if (\extension_loaded('mbstring') && \mb_strlen($this->alphabet) !== $alphabetLength) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }

        $startLength = $alphabetLength;
        $bits = 0;
        do {
            $bits++;
        } while (($startLength >>= 1) > 1);

        $this->alphabetBits = $bits;

        $this->maxEncodedLength = $this->getMaxEncodedLength();
    }

    public function encode(int $id): string
    {
        if ($id < 0) {
            throw new IdEncodeException('Encrypted ID must be positive.');
        }

        $mask = (1 << $this->alphabetBits) - 1;
        $reminder = $id & $mask;
        $quotient = $id >> $this->alphabetBits;
        $output = \substr($this->alphabet, $reminder, 1);

        while ($quotient > 0) {
            $reminder = $quotient & $mask;
            $quotient >>= $this->alphabetBits;

            $output .= \substr($this->alphabet, $reminder, 1);
        }

        return \strrev($output);
    }

    public function decode(string $id): int
    {
        if (\strlen($id) === 0 || \strlen($id) > $this->maxEncodedLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        if ((int) preg_match('/^[' . preg_quote($this->alphabet, '/') . ']+$/', $id) === 0) {
            throw new IdDecodeException('Id has invalid characters');
        }

        $number = 0;
        foreach (\str_split($id) as $char) {
            $number = ($number << $this->alphabetBits) + (int) \strpos($this->alphabet, $char);
        }

        if ($number < 0 || \is_float($number)) { // @phpstan-ignore function.impossibleType (this can be float type when overflowed)
            throw new IdDecodeException('Decrypted ID is out of valid range');
        }

        return $number;
    }

    public function getMaxEncodedLength(): int
    {
        return \strlen($this->encode(PHP_INT_MAX));
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }
}
