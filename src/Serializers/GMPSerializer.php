<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * This class needs gmp extension. Supports custom length alphabet so could provide smaller output than NativeSerializer.
 * It's recommended to set shuffled version of the alphabet (e.g. with str_shuffle()) or use your own.
 * Interoperable with GMPSerializer.
 */
class GMPSerializer implements SerializerContract
{
    use HasSerializerMaxOutput;

    private int $maxLength;

    /**
     * Shuffled alphabet with removed e, E, a, A, e, i, I, o, O, l, u, U, 0 to lower probability of building random words.
     * Max 12 chars output length.
     */
    public const SAFE_ALPHABET = 'd7x3Db2LY5Qgv1k9wZFfKMCztc6nPh4XyBsJ8VRpNSHTmqjGrW';

    /**
     * Same as safe alphabet but added specialcharacters _-l.~!*. Max 11 chars output length.
     */
    public const EXTENDED_ALPHABET = 'd7x3Db2LY5Qgv1k9wZFfKMCztc6nPh4XyBsJ8VRpNSHTmqjGrW_-l.~!*';

    /**
     * @param string $alphabet Supports any length > 1
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(private string $alphabet = self::SAFE_ALPHABET)
    {
        if (extension_loaded('gmp') === false) {
            throw new \RuntimeException('GMP extension not installed');
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
            $num = \gmp_add($num, \gmp_mul($data[3], 281474976710656)); // num2 1 << 48
            [$quotient, $reminder] = \gmp_div_qr($num, $alphabetLength); // @phpstan-ignore offsetAccess.nonArray
            $quotient = (int) gmp_strval($quotient);
            $reminder = (int) gmp_strval($reminder);
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
        if (is_float($numberInt) === false) { // @phpstan-ignore function.impossibleType,identical.alwaysTrue (this can be float)
            // we're still in signed int range, can go without gmp
            $reminder = $numberInt & $mask;
            $quotient = $numberInt >> 16;
        } else {
            $number = \gmp_add(\gmp_mul($number, $alphabetLength), $index); // @phpstan-ignore argument.type (this can't be false)
            // num2 1 << 16
            [$quotient, $reminder] = \gmp_div_qr($number, 65536); // @phpstan-ignore offsetAccess.nonArray
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
