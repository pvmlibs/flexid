<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * This serializer is intended especially for short, sequential id to hide their incremental nature and still create
 * short output. Encoding can be eventually reverted with some work so this is not secure solution, it will only look
 * random. For better confidentiality see encryption. Works with custom alphabets with length at least 32 characters,
 * the default one has optimized length and should not create words, but remember to use shuffled version before use.
 * Id capacity for output length with default alphabet length:
 * 3 chars: 65k
 * 4 chars: ~4mln millions
 * 5 chars: ~250 millions
 * 6 chars: ~13 billions.
 */
class HashSerializer implements SerializerContract
{
    private int $offset;

    // optimized alphabet with no vowels
    public const ALPHABET = 'gc2bqRf6Q3Mvm4YnWtJy7KPzGTBVwN8XrCjkZ1hxHDdF9LpsS';

    private int $maxOutputLength;

    private string $unrolledAlphabet;

    /** @var array<int> */
    private array $keyHashes = [];

    /** @var array<int> */
    private array $keyHashesInv = [];

    /**
     * @param string $alphabet  pass your own shuffled alphabet, otherwise it will be easy to reverse encoded id
     * @param int    $minLength Minimum length of encoded id. This will work for >=4, lower values can return 1-3 chars output.
     *                          This will truncate upper range proportionally, you can check max range with getMaxRange().
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[\SensitiveParameter]
        private string $alphabet = self::ALPHABET,
        int $minLength = 4,
    ) {
        $alphabetLength = \strlen($this->alphabet);

        if ($alphabetLength < 32 || $alphabetLength > 94) {
            throw new \InvalidArgumentException('Alphabet length must be between 32 and 94');
        }

        if (\extension_loaded('mbstring') && mb_strlen($alphabet) !== $alphabetLength) {
            throw new \InvalidArgumentException('Alphabet must not contain multi byte characters');
        }

        if (\preg_match('/(.).*\1/', $alphabet) === 1) {
            throw new \InvalidArgumentException('Alphabet must contain unique characters');
        }

        $this->maxOutputLength = (int) ceil(log(PHP_INT_MAX, $alphabetLength));

        if ($minLength < 1 || $minLength > ($this->maxOutputLength - 1)) {
            throw new \InvalidArgumentException('Target length must be >= 2 and < ' . $this->maxOutputLength);
        }

        // unroll to allow operating without modulus with rotation
        $this->unrolledAlphabet = $this->alphabet . $this->alphabet;

        if ($minLength <= 3) {
            $this->offset = 0;
        } else {
            $this->offset = pow(\strlen($this->alphabet), $minLength - 1) << 1;
        }

        $key = 0;
        for ($i = $alphabetLength - 1; $i >= $alphabetLength - 9; $i--) { // 9 max length with base 94
            $key = 94 * $key + (\ord($this->alphabet[$i]) - 32);
        }

        for ($i = 0; $i < 4; $i++) {
            $this->keyHashes[$i] = ($key >> (16 * $i) & 0xFFFF) | 1;
            $this->keyHashesInv[$i] = $this->modInverse16($this->keyHashes[$i]);
        }
    }

    public function serialize(int $data): string
    {
        if ($data < 0) {
            throw new IdEncodeException('Id must be positive');
        }
        if (\is_float($data = $data + $this->offset)) { // @phpstan-ignore function.impossibleType,identical.alwaysTrue (this can be float)
            throw new IdEncodeException('Id range is exhausted');
        }
        $firstBlock = $data & 0xFFFF;

        // hash first 16-bit block
        for ($i = 0; $i < 4; $i++) {
            $firstBlock ^= $firstBlock >> 8;
            $firstBlock *= $this->keyHashes[$i];
            $firstBlock &= 0xFFFF;
        }

        $number = ($data & ~0xFFFF) | $firstBlock;

        // mix firsts block with rest of 16-bit blocks
        if ($number > 0xFFFFFFFFFFFF) { // $number >= 2^48
            $number = ($number & -281470681743361) | ((($number >> 32) ^ $firstBlock) << 32); // mask ~(0xFFFF << 32)
            $number = ($number & -4294901761) | ((($number >> 16) ^ $firstBlock) << 16); // mask ~(0xFFFF << 16)
            $number = $this->partialXor($number, 48, $firstBlock);
        } elseif ($number > 0xFFFFFFFF) { // $number < 2^48 && $number >= 2^32
            $number = ($number & -4294901761) | ((($number >> 16) ^ $firstBlock) << 16); // mask ~(0xFFFF << 16)
            $number = $this->partialXor($number, 32, $firstBlock);
        } elseif ($number > 0xFFFF) {
            $number = $this->partialXor($number, 16, $firstBlock);
        }

        // encode with alphabet
        $alphabetLength = \strlen($this->alphabet);
        $quotient = \intdiv($number, $alphabetLength);
        $rotate = $number % $alphabetLength;

        $result = $this->unrolledAlphabet[$rotate];

        if ($quotient === 0) {
            return $result;
        }

        while ($quotient >= $alphabetLength) {
            $reminder = $quotient % $alphabetLength;
            $quotient = \intdiv($quotient, $alphabetLength);
            $result .= $this->unrolledAlphabet[$reminder + $rotate];
        }

        // here is always >= 1 chars, this will be the last, 2 div less.
        $result .= $this->unrolledAlphabet[$quotient + $rotate];

        return $result;
    }

    public function deserialize(string $data): int
    {
        $length = \strlen($data);

        if ($data === '' || $length > $this->maxOutputLength) {
            throw new IdDecodeException('Id has incorrect length');
        }

        $firstCharPos = \strpos($this->alphabet, $data[0]);
        if ($firstCharPos === false) {
            throw new IdDecodeException('Id has invalid characters');
        }

        $alphabetLength = \strlen($this->alphabet);
        $rotate = $firstCharPos;
        $number = 0;

        for ($i = $length - 1; $i > 0; $i--) {
            $index = \strpos($this->alphabet, $data[$i]);

            if ($index === false) {
                throw new IdDecodeException('Id has invalid characters');
            }
            $index = ($alphabetLength + $index - $rotate) % $alphabetLength;

            $number = $number * $alphabetLength + $index;
        }

        $number = $number * $alphabetLength + $firstCharPos;

        if (\is_float($number) === true) { // @phpstan-ignore function.impossibleType,identical.alwaysFalse (this can be float)
            throw new IdDecodeException('Id out of range');
        }

        $firstBlock = $number & 0xFFFF;

        if ($number > 0xFFFFFFFFFFFF) { // $number >= 2^48
            $number = ($number & -281470681743361) | ((($number >> 32) ^ $firstBlock) << 32); // mask ~(0xFFFF << 32)
            $number = ($number & -4294901761) | ((($number >> 16) ^ $firstBlock) << 16); // mask ~(0xFFFF << 16)
            $number = $this->partialXor($number, 48, $firstBlock);
        } elseif ($number > 0xFFFFFFFF) { // $number < 2^48 && $number >= 2^32
            $number = ($number & -4294901761) | ((($number >> 16) ^ $firstBlock) << 16); // mask ~(0xFFFF << 16)
            $number = $this->partialXor($number, 32, $firstBlock);
        } elseif ($number > 0xFFFF) {
            $number = $this->partialXor($number, 16, $firstBlock);
        }

        // inverse first block hash
        for ($i = 3; $i >= 0; $i--) {
            $firstBlock *= $this->keyHashesInv[$i];
            $firstBlock &= 0xFFFF;
            $firstBlock ^= $firstBlock >> 8;
        }

        $id = ($number & ~0xFFFF) | $firstBlock;

        $id = $id - $this->offset;

        if ($id < 0) { // @phpstan-ignore function.impossibleType,identical.alwaysTrue (this can be float)
            throw new IdDecodeException('Id is out of range');
        }

        return $id;
    }

    private function modInverse16(int $x): int
    {
        $mask = 0xFFFF;
        $y = (3 * $x) ^ 2;
        $y = $y * (2 - $y * $x);
        $y &= $mask;
        $y = $y * (2 - $y * $x);
        $y &= $mask;

        return $y;
    }

    private function partialXor(int $input, int $shift, int $firstBlock): int
    {
        $block = ($input >> $shift) & 0xFFFF;

        if ($block < 256) {
            $i = 7;
        } else {
            $i = 15;
        }

        for (; $i > 0; $i--) {
            if (($block & (1 << $i)) > 0) {
                $mask = ((1 << $i) - 1);
                $block = ($block ^ $firstBlock) & $mask;

                return ($input & ~($mask << $shift)) | ($block << $shift);
            }
        }

        return $input;
    }

    public function getMaxEncodedLength(): int
    {
        return $this->maxOutputLength;
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
