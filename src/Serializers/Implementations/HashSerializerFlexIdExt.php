<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers\Implementations;

use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Tools\Alphabets;

/**
 * Faster version of HashSerializerPHP, using flexid_utils extension.
 */
class HashSerializerFlexIdExt implements SerializerContract
{
    private \FlexId\HashEncoder $encoder;
    private int $offset;

    /**
     * @param string $alphabet  pass your own shuffled alphabet, otherwise it will be easy to reverse encoded id
     * @param int    $minLength Minimum length of encoded id. This will work for >=4, lower values can return 1-3 chars output.
     *                          This will truncate upper range proportionally, you can check max range with getMaxRange().
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private string $alphabet = Alphabets::CUSTOM_SAFE_ALPHABET,
        int $minLength = 4,
    ) {
        $alphabetLength = \strlen($this->alphabet);

        if ($alphabetLength < 9 || $alphabetLength > 94 || ($alphabetLength & ($alphabetLength - 1)) === 0) {
            throw new \InvalidArgumentException('Alphabet length must be between 9 and 94 and not power of 2');
        }

        if (\extension_loaded('mbstring') && mb_strlen($alphabet) !== \strlen($alphabet)) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }

        $maxOutputLength = (int) \ceil(\log(PHP_INT_MAX, $alphabetLength));

        if ($minLength < 1 || $minLength > ($maxOutputLength - 1)) {
            throw new \InvalidArgumentException('Target length must be >= 2 and < ' . $maxOutputLength);
        }

        if ($minLength <= 3) {
            $this->offset = 0;
        } else {
            $this->offset = \pow($alphabetLength, $minLength - 1) << 1;
            // for low alphabet lengths, need to bump above first 16 bits
            if ($this->offset < 0x10000) {
                $this->offset = 0x10000;
            }
        }

        try {
            $this->encoder = new \FlexId\HashEncoder($this->alphabet, $this->offset);
        } catch (\ValueError $ex) {
            throw new \InvalidArgumentException($ex->getMessage());
        }
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
            throw new IdDecodeException($e->getMessage());
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

    public function getMaxRange(): int
    {
        return PHP_INT_MAX - $this->offset;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }
}
