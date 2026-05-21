<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encoders;

use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Convert integer ID to/from shorter text format through encoding. This is not encryption of any kind. Main purposes:
 *  - provide as short string from int id as possible, given provided alphabet
 *  - probability of building random words should be very low, especially profanity ones, but we also don't want to maintain
 *    custom dictionary with blacklisted words.
 *  - encoded id should look random, even if encoding sequential numbers
 *  - fast encode/decode
 * If you want id as short as possible and don't care about building random words, you can add more chars to alphabet,
 * but remember that once set, along with offset they can't be changed, or you won't be able to decode id that was encoded
 * with different parameters.
 *
 * Encoding uses obfuscation with custom alphabet and additional offset for rotating and should not be considered as
 * secure solution for critical paths for hiding raw id. This is provided as best effort without using cryptography,
 * but still encoded id could be reversed with some work. For more secure solution see Sparx64Encrypter.
 * Encoding is however still better than just providing raw id. Set shuffled version of the alphabet
 * (e.g. with str_shuffle() and offset) to get best results.
 */
class PseudoRandomEncoder implements EncoderContract
{
    /**
     * Shuffled alphabet with removed e, E, a, A, e, i, I, o, O, l, u, U, 0 to lower probability of building random words.
     */
    public const SAFE_ALPHABET = 'd7x3Db2LY5Qgv1k9wZFfKMCztc6nPh4XyBsJ8VRpNSHTmqjGrW';

    /**
     * Shuffled 64 chars, a-zA-Z0-9-_.
     */
    public const EXTENDED_ALPHABET = 'R9jHJSAcLBfFroKqTzXvgxtNbsp83DEkVMIn-y0m5hdG_P47awQl26uYZUeW1iCO';
    private int $maxEncodedLength;

    /**
     * @param string $alphabet It's recommended to use shuffled version of default alphabet.
     *                         Set once for application lifetime.
     * @param int    $offset   Additional offset used for rotating alphabet.
     *                         Set once for application lifetime.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private string $alphabet = self::SAFE_ALPHABET,
        private int $offset = 0,
    ) {
        if (\strlen($alphabet) < 2) {
            throw new \InvalidArgumentException('Alphabet length must be at least 2');
        }

        if (\preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        if (\extension_loaded('mbstring') && mb_strlen($this->alphabet) !== \strlen($alphabet)) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }

        $this->maxEncodedLength = $this->getMaxEncodedLength();
    }

    public function encode(int $id): string
    {
        if ($id < 0) {
            throw new IdEncodeException('Encrypted ID must be positive.');
        }

        $alphabetLength = \strlen($this->alphabet);
        $quotient = $id;

        $reminder = $quotient % $alphabetLength;
        $quotient = \intdiv($quotient, $alphabetLength);

        $output = \substr($this->alphabet, $reminder, 1);
        $rotate = \ord($output) + $this->offset;

        while ($quotient > 0) {
            $reminder = $quotient % $alphabetLength;
            $quotient = \intdiv($quotient, $alphabetLength);

            $output .= \substr($this->alphabet, ($rotate + $reminder) % $alphabetLength, 1);
        }

        return $output;
    }

    public function decode(string $id): int
    {
        if (\strlen($id) === 0 || \strlen($id) > $this->maxEncodedLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        if ((int) \preg_match('/^[' . \preg_quote($this->alphabet, '/') . ']+$/', $id) === 0) {
            throw new IdDecodeException('Id has invalid characters');
        }

        $rotate = \ord($id[0]) + $this->offset;
        $number = 0;
        $alphabetLength = \strlen($this->alphabet);

        $split = \str_split($id);

        for ($i = \count($split) - 1; $i; $i--) {
            $index = (int) \strpos($this->alphabet, $split[$i]);

            $newIndex = ($index - $rotate) % $alphabetLength;

            if ($newIndex < 0) {
                $newIndex = $alphabetLength + $newIndex;
            }

            $number = $number * $alphabetLength + $newIndex;
        }

        $index = (int) \strpos($this->alphabet, $split[0]);

        $number = $number * $alphabetLength + $index;

        if ($number < 0 || is_float($number)) { // @phpstan-ignore function.impossibleType (this can be float type when overflowed)
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
