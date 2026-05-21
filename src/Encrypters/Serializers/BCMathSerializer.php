<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * This class needs bc-math extension. Supports custom length alphabet.
 * It's recommended to set you own shuffled version of the alphabet (e.g. with str_shuffle()).
 * About 20% slower than NativeSerializer.
 */
class BCMathSerializer implements SerializerContract
{
    private int $maxLength;

    /**
     * Shuffled alphabet with removed e, E, a, A, e, i, I, o, O, l, u, U, 0 to lower probability of building random words.
     */
    public const SAFE_ALPHABET = 'd7x3Db2LY5Qgv1k9wZFfKMCztc6nPh4XyBsJ8VRpNSHTmqjGrW';

    /**
     * Shuffled 64 chars, a-zA-Z0-9-_.
     */
    public const EXTENDED_ALPHABET = 'R9jHJSAcLBfFroKqTzXvgxtNbsp83DEkVMIn-y0m5hdG_P47awQl26uYZUeW1iCO';

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

        $this->maxLength = $this->getMaxEncodedLength();
    }

    public function serialize(array $data): string
    {
        $num = $data[0];
        $num |= $data[1] << 16;
        $num |= $data[2] << 32;
        $num = \bcadd((string) $num, \bcmul((string) $data[3], (string) (1 << 48)));

        $alphabetLength = (string) \strlen($this->alphabet);
        $result = '';
        $quotient = $num;

        do {
            [$quotient, $reminder] = \bcdivmod($quotient, $alphabetLength); // @phpstan-ignore offsetAccess.nonArray
            $result .= substr($this->alphabet, \intval($reminder), 1);
        } while ($quotient !== '0');

        return $result;
    }

    public function deserialize(string $data): array
    {
        // convert from alphabet to bytes
        if ($data === '' || \strlen($data) > $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        if ((int) \preg_match('/^[' . \preg_quote($this->alphabet, '/') . ']+$/', $data) === 0) {
            throw new IdDecodeException('Id has invalid characters');
        }

        $number = '0';
        $alphabetLength = (string) \strlen($this->alphabet);

        foreach (\str_split(strrev($data)) as $char) {
            $number = \bcadd(\bcmul($number, $alphabetLength), (string) strpos($this->alphabet, $char)); // @phpstan-ignore argument.type (this can't be false)
        }

        [$quotient, $reminder] = \bcdivmod($number, (string) (1 << 16)); // @phpstan-ignore offsetAccess.nonArray
        $quotient = (int) $quotient;

        $mask = ((1 << 16) - 1);

        return [
            (int) $reminder,
            $quotient & $mask,
            ($quotient >> 16) & $mask,
            ($quotient >> 32) & $mask,
        ];
    }

    public function getMaxEncodedLength(): int
    {
        return \strlen($this->serialize([
            0xFFFF, 0xFFFF, 0xFFFF, 0xFFFF,
        ]));
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }
}
