<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers\Implementations;

use Pvmlibs\FlexId\Contracts\IntegerOperationsContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\IntegerOperations\PositiveIntegersOnly;
use Pvmlibs\FlexId\Tools\Alphabets;

/**
 * Serialize any number (positive and negative - with IntegerOperationsContract) to string with custom length alphabet.
 * Main purposes:
 *  - provide as short string from int id as possible, given provided alphabet
 *  - probability of building random words should be very low, especially profanity ones, default alphabet has removed vowels.
 *  - intended to use with output from encrypters/signers (whole range).
 *
 * This is not intended for security. For confidentiality use encryption. If you want to just obfuscate id, HashSerializer
 * will be better.
 */
class CustomSerializerPHP implements SerializerContract
{
    private int $maxLength;

    private IntegerOperationsContract $integerOperations;

    /**
     * @param string $alphabet Supports lengths <2, 94>
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        ?IntegerOperationsContract $fullRangeIntegerOperations = null,
        private string $alphabet = Alphabets::CUSTOM_SAFE_ALPHABET,
    ) {
        if (\strlen($alphabet) < 2 || \strlen($alphabet) > 94) {
            throw new \InvalidArgumentException('Alphabet length must be <2, 94>');
        }

        if (\preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        if (\extension_loaded('mbstring') && mb_strlen($alphabet) !== \strlen($alphabet)) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }

        if ($fullRangeIntegerOperations === null) {
            $this->integerOperations = new PositiveIntegersOnly();
        } else {
            $this->integerOperations = $fullRangeIntegerOperations;
        }

        if ($this->integerOperations instanceof PositiveIntegersOnly) {
            $maxSerialized = $this->serialize(PHP_INT_MAX); // max range for positive id
            $this->maxLength = \strlen($maxSerialized);
        } else {
            $maxSerialized = $this->serialize(-1); // max range for full range id
            $this->maxLength = \strlen($maxSerialized);
        }
    }

    public function serialize(int $data): string
    {
        $alphabetLength = \strlen($this->alphabet);

        [$quotient, $reminder] = $this->integerOperations->divmod($data, $alphabetLength);

        $result = $this->alphabet[$reminder];

        if ($quotient === 0) {
            return $result;
        }

        while ($quotient >= $alphabetLength) {
            $reminder = $quotient % $alphabetLength;
            $quotient = \intdiv($quotient, $alphabetLength);

            $result .= $this->alphabet[$reminder];
        }

        // here always >= 1 chars, this will be last, 2 div less.
        $result .= $this->alphabet[$quotient];

        return $result;
    }

    public function deserialize(string $data): int
    {
        if ($data === '' || \strlen($data) > $this->maxLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        $alphabetLength = \strlen($this->alphabet);

        $firstCharPos = \strpos($this->alphabet, $data[0]);
        if ($firstCharPos === false) {
            throw new IdDecodeException('Id has invalid characters');
        }

        $number = 0;

        for ($i = \strlen($data) - 1; $i > 0; $i--) {
            $index = \strpos($this->alphabet, $data[$i]);

            if ($index === false) {
                throw new IdDecodeException('Id has invalid characters');
            }

            $number = $number * $alphabetLength + $index;
        }

        // now $number still have to be int type, but last multiplication and add could be out of range
        return $this->integerOperations->addmul($firstCharPos, $number, $alphabetLength);
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxLength;
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }
}
