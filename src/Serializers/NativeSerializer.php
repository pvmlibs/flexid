<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * Don't require any extensions but supports only alphabet with power of 2 length.
 * It's recommended to set shuffled version of the alphabet (e.g. with str_shuffle()) or use your own.
 * Fastest serializer.
 */
class NativeSerializer implements SerializerContract
{
    use HasSerializerMaxOutput;

    private readonly int $alphabetLength;
    private readonly int $alphabetBits;

    private readonly int $maxLength;

    /**
     * 32 chars, shuffled alphabet with removed common vowels and digits to lower probability of building random words.
     * Max 13 chars output length.
     */
    public const SAFE_ALPHABET = 'FDxkwdMKQRGgCBPLpYmvVJyXZbjczWqf';

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

        $this->alphabetBits = \intval(log($this->alphabetLength, 2));
        $this->maxLength = $this->maxOutputs[\strlen($alphabet)];
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

        $number = 0;
        for ($i = \strlen($data) - 1; $i >= 0; $i--) {
            $index = \strpos($this->alphabet, $data[$i]);
            if ($index === false) {
                throw new IdDecodeException('Id has invalid characters');
            }
            $number = ($number << $this->alphabetBits) + $index;
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
