<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * Don't require any extensions but supports only alphabet with power of 2 length.
 * It's recommended to set you own shuffled version of the alphabet (e.g. with str_shuffle() and offset).
 */
class NativeSerializer implements SerializerContract
{
    private readonly int $alphabetLength;
    private readonly int $alphabetBits;

    private readonly int $maxLength;

    /**
     * 32 chars, shuffled alphabet with removed common vowels and digits to lower probability of building random words.
     */
    public const SAFE_ALPHABET = 'FDxkwdMKQRGgCBPLpYmvVJyXZbjczWqf';

    /**
     * Shuffled 64 chars, a-zA-Z0-9-_.
     */
    public const EXTENDED_ALPHABET = 'R9jHJSAcLBfFroKqTzXvgxtNbsp83DEkVMIn-y0m5hdG_P47awQl26uYZUeW1iCO';

    /**
     * @param string $alphabet Length must be power of 2
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private string $alphabet = self::SAFE_ALPHABET,
    ) {
        $this->alphabetLength = \strlen($this->alphabet);

        if ($this->alphabetLength < 2 || ($this->alphabetLength & ($this->alphabetLength - 1)) !== 0) {
            throw new \InvalidArgumentException('Invalid alphabet length, must be power of 2, got ' . $this->alphabetLength);
        }

        if (\preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        if (\extension_loaded('mbstring') && mb_strlen($this->alphabet) !== $this->alphabetLength) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }

        $startLength = $this->alphabetLength;
        $bits = 0;
        do {
            $bits++;
        } while (($startLength >>= 1) > 1);

        $this->alphabetBits = $bits;
        $this->maxLength = $this->getMaxEncodedLength();
    }

    public function serialize(array $data): string
    {
        $mask = (1 << $this->alphabetBits) - 1;
        $reminder = $data[0] & $mask;
        $quotient = ($data[0] >> $this->alphabetBits)
            | ($data[1] << 16 - $this->alphabetBits)
            | ($data[2] << 32 - $this->alphabetBits)
            | ($data[3] << 48 - $this->alphabetBits);

        $result = \substr($this->alphabet, $reminder, 1);

        while ($quotient > 0) {
            $reminder = $quotient & $mask;
            $quotient >>= $this->alphabetBits;
            $result .= \substr($this->alphabet, $reminder, 1);
        }

        return $result;
    }

    public function deserialize(string $data): array
    {
        if ($data === '' || \strlen($data) > $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        if ((int) \preg_match('/^[' . \preg_quote($this->alphabet, '/') . ']+$/', $data) === 0) {
            throw new IdDecodeException('Id has invalid characters');
        }

        $number = 0;
        foreach (\str_split(\strrev($data)) as $char) {
            $number = ($number << $this->alphabetBits) + \strpos($this->alphabet, $char); // @phpstan-ignore plus.rightNonNumeric (strpos can't be false)
        }

        $mask = ((1 << 16) - 1);

        return [
            $number & $mask,
            ($number >> 16) & $mask,
            ($number >> 32) & $mask,
            ($number >> 48) & $mask,
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
