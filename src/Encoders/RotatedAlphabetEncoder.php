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
class RotatedAlphabetEncoder implements EncoderContract
{
    use HasEncoderMaxOutput;

    /**
     * Shuffled alphabet with removed e, E, a, A, e, i, I, o, O, l, u, U, 0 to lower probability of building random words.
     * Max 12 chars output length.
     */
    public const SAFE_ALPHABET = 'd7x3Db2LY5Qgv1k9wZFfKMCztc6nPh4XyBsJ8VRpNSHTmqjGrW';

    private int $maxEncodedLength;
    private string $unrolledAlphabet;

    /**
     * @param string $alphabet It's recommended to use shuffled version of default alphabet or use your own.
     *                         Set once for application lifetime.
     * @param int    $offset   additional offset used for rotating alphabet
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private string $alphabet = self::SAFE_ALPHABET,
        private int $offset = 0,
    ) {
        $alphabetLength = strlen($this->alphabet);

        if ($alphabetLength < 2) {
            throw new \InvalidArgumentException('Alphabet length must be at least 2');
        }

        if (\preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        if (\extension_loaded('mbstring') && mb_strlen($this->alphabet) !== $alphabetLength) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }

        if ($offset < 0 || $this->offset >= $alphabetLength) {
            throw new \InvalidArgumentException("Offset mus be within <0, {$alphabetLength})");
        }

        // unroll to allow operating without modulus
        $this->unrolledAlphabet = $this->alphabet . $this->alphabet . $this->alphabet;
        $this->maxEncodedLength = $this->maxOutputs[$alphabetLength];
    }

    public function encode(int $id): string
    {
        if ($id < 0) {
            throw new IdEncodeException('Encoded ID must be positive.');
        }

        $alphabetLength = \strlen($this->alphabet);

        $reminder = $id % $alphabetLength;
        $quotient = \intdiv($id, $alphabetLength);

        $output = $this->alphabet[$reminder];
        $rotate = $reminder + $this->offset;

        while ($quotient > 0) {
            $reminder = $quotient % $alphabetLength;
            $quotient = \intdiv($quotient, $alphabetLength);
            $output .= $this->unrolledAlphabet[$rotate + $reminder];
        }

        return $output;
    }

    public function decode(string $id): int
    {
        if (\strlen($id) === 0 || \strlen($id) > $this->maxEncodedLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        $alphabetLength = \strlen($this->alphabet);
        $firstCharPos = \strpos($this->alphabet, $id[0]);

        if ($firstCharPos === false) {
            throw new IdDecodeException('Id has invalid characters');
        }

        $rotate = ($firstCharPos + $this->offset) % $alphabetLength;
        $number = 0;

        for ($i = \strlen($id) - 1; $i; $i--) {
            $index = \strpos($this->alphabet, $id[$i]);

            if ($index === false) {
                throw new IdDecodeException('Id has invalid characters');
            }

            $newIndex = $index - $rotate;
            if ($newIndex < 0) {
                $newIndex = $alphabetLength + $newIndex;
            }

            $number = $number * $alphabetLength + $newIndex;
        }

        // first character without rotate
        $number = $number * $alphabetLength + $firstCharPos;

        if ($number < 0 || is_float($number)) { // @phpstan-ignore function.impossibleType (this can be float type when overflowed)
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
