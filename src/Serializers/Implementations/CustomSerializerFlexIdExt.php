<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers\Implementations;

use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Tools\Alphabets;

/**
 * Serialize any number to string with custom length alphabet.
 * Main purposes:
 *  - provide as short string from int id as possible, given provided alphabet
 *  - probability of building random words should be very low, especially profanity ones, default alphabet has removed vowels.
 *  - intended to use with output from encrypters/signers (whole range).
 *
 * This is not intended for security. For confidentiality use encryption. If you want to just obfuscate id, HashSerializer
 * will be better.
 */
class CustomSerializerFlexIdExt implements SerializerContract
{
    private \FlexId\Encoder $encoder;

    /**
     * @param string $alphabet Supports lengths <2, 94>
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
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

        $this->encoder = new \FlexId\Encoder($this->alphabet);
    }

    public function serialize(int $data): string
    {
        try {
            return $this->encoder->encode($data);
        } catch (\ValueError $e) {
            throw new IdEncodeException($e->getMessage());
        }
    }

    public function deserialize(string $data): int
    {
        try {
            return $this->encoder->decode($data);
        } catch (\ValueError $e) {
            throw new IdDecodeException($e->getMessage() . $data);
        }
    }

    public function getMaxEncodedLength(): int
    {
        return $this->encoder->getMaxEncodedLength();
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }
}
