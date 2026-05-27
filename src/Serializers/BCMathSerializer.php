<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * This class needs bc-math extension. Supports custom length alphabet so could provide smaller output than NativeSerializer.
 * It's recommended to set shuffled version of the alphabet (e.g. with str_shuffle()) or use your own.
 * Interoperable with GMPSerializer.
 */
class BCMathSerializer implements SerializerContract
{
    use HasSerializerMaxOutput;

    private int $maxLength;

    /**
     * Shuffled alphabet with removed e, E, a, A, e, i, I, o, O, l, u, U, 0 to lower probability of building random words.
     * Max 12 chars output length.
     */
    public const SAFE_ALPHABET = 'd7x3Db2LY5Qgv1k9wZFfKMCztc6nPh4XyBsJ8VRpNSHTmqjGrW';

    /**
     * Same as safe alphabet but added characters _-l.~!*. Max 11 chars output length.
     */
    public const EXTENDED_ALPHABET = 'd7x3Db2LY5Qgv1k9wZFfKMCztc6nPh4XyBsJ8VRpNSHTmqjGrW_-l.~!*';

    /**
     * @param string $alphabet Supports any length > 1
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(private string $alphabet = self::SAFE_ALPHABET)
    {
        if (extension_loaded('bcmath') === false) {
            throw new \RuntimeException('BCMath extension not installed');
        }

        if (\strlen($alphabet) < 2) {
            throw new \InvalidArgumentException('Alphabet length must be at least 2');
        }

        if (\preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        if (\extension_loaded('mbstring') && mb_strlen($this->alphabet) !== \strlen($alphabet)) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }

        $this->maxLength = $this->maxOutputs[\strlen($alphabet)];
    }

    public function serialize(array $data): string
    {
        if ($data[0] > 0xFFFF || $data[1] > 0xFFFF || $data[2] > 0xFFFF || $data[3] > 0xFFFF) {
            throw new IdEncodeException('Out of range value for serializer');
        }

        $alphabetLength = \strlen($this->alphabet);

        $num = $data[0];
        $num |= $data[1] << 16;
        $num |= $data[2] << 32;

        if (($data[3] & 32768) > 0) {
            // needs 64 bits so use bcmath
            $num = \bcadd((string) $num, \bcmul((string) $data[3], '281474976710656')); // num2 1 << 48
            [$quotient, $reminder] = \bcdivmod($num, (string) $alphabetLength); // @phpstan-ignore offsetAccess.nonArray
            $quotient = (int) $quotient;
            $reminder = (int) $reminder;
        } else {
            // when last bit is not set then we can fit the number in 63 bit and process it directly
            $num |= $data[3] << 48;
            $reminder = $num % $alphabetLength;
            $quotient = \intdiv($num, $alphabetLength);
        }

        $result = $this->alphabet[$reminder];

        while ($quotient > 0) {
            $reminder = $quotient % $alphabetLength;
            $quotient = \intdiv($quotient, $alphabetLength);

            $result .= $this->alphabet[$reminder];
        }

        return $result;
    }

    public function deserialize(string $data): array
    {
        if ($data === '' || \strlen($data) > $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        $number = 0;
        $alphabetLength = \strlen($this->alphabet);

        for ($i = \strlen($data) - 1; $i > 0; $i--) {
            $index = \strpos($this->alphabet, $data[$i]);
            if ($index === false) {
                throw new IdDecodeException('Id has invalid characters');
            }

            $number = $number * $alphabetLength + $index;
        }

        // last one may be out of range
        $index = \strpos($this->alphabet, $data[0]);
        if ($index === false) {
            throw new IdDecodeException('Id has invalid characters');
        }

        $mask = 0xFFFF;
        $numberInt = $number * $alphabetLength + $index;
        if (\is_float($numberInt) === false) { // @phpstan-ignore function.impossibleType,identical.alwaysTrue (this can be float)
            // we're still in signed int range, can go without bcmath
            $reminder = $numberInt & $mask;
            $quotient = $numberInt >> 16;
        } else {
            $number = \bcadd(\bcmul((string) $number, (string) $alphabetLength), (string) $index); // @phpstan-ignore argument.type (this can't be false)
            // num2 1 << 16
            [$quotient, $reminder] = \bcdivmod($number, '65536'); // @phpstan-ignore offsetAccess.nonArray
            $quotient = (int) $quotient;
            $reminder = (int) $reminder;
        }

        return [
            $reminder,
            $quotient & $mask,
            ($quotient >> 16) & $mask,
            ($quotient >> 32) & $mask,
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
        return false;
    }
}
