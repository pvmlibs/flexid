<?php

declare(strict_types=1);

namespace Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Serializers\Implementations\HashSerializerPHP;
use Pvmlibs\FlexId\Tools\Alphabets;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;
use Tests\Serializers\HasSerializerTesting;

/**
 * @internal
 */
final class HashSerializerPHPTest extends TestCase
{
    use HasSerializerTesting;
    use HasBackwardCompatibilityTesting;
    use HasCharDistributionTesting;

    public function testSerializeDeserialize(): void
    {
        $serializer = new HashSerializerPHP(minLength: 1);
        // test whole 16-bit range
        $this->validateSerializeDeserialize(serializer: $serializer, positivesOnly: true, incrementalRange: 70000);

        $range = 100;
        foreach ($this->alphabets(9) as $alphabet) {
            if ((strlen($alphabet) & (strlen($alphabet) - 1)) === 0) {
                continue;
            }

            $serializer = new HashSerializerPHP(alphabet: $alphabet, minLength: 1);
            $this->validateSerializeDeserialize(serializer: $serializer, positivesOnly: true, incrementalRange: 1000);
            $this->validateBlocks($serializer, 0xFFFF, 0xFFFF + 1000);

            // first and last bit set for 2nd 16 bit block
            $this->validateBlocks($serializer, 0x10000, 0x10000 + $range);
            $this->validateBlocks($serializer, 0x80000000, 0x80000000 + $range);

            // first and last bit set for 3rd 16 bit block
            $this->validateBlocks($serializer, 0x100000000, 0x100000000 + $range);
            $this->validateBlocks($serializer, 0x800000000000, 0x800000000000 + $range);

            // last bit set for 3rd 16 bit block, first will give negative
            $this->validateBlocks($serializer, 0x1000000000000, 0x1000000000000 + $range);

            $this->validateOutOfRange($alphabet, $serializer, PHP_INT_MAX);
        }
    }

    public function testSerializeDeserializeWithMinLength(): void
    {
        for ($m = 4; $m <= 10; $m++) {
            $serializer = new HashSerializerPHP(minLength: $m);
            $this->validateSerializeDeserialize(serializer: $serializer, positivesOnly: true, incrementalRange: 1000, rangeMax: $serializer->getMaxRange());

            foreach ($this->alphabets(9) as $alphabet) {
                if ((strlen($alphabet) & (strlen($alphabet) - 1)) === 0) {
                    continue;
                }

                $serializer = new HashSerializerPHP(alphabet: $alphabet, minLength: $m);
                $this->validateSerializeDeserialize(serializer: $serializer, positivesOnly: true, incrementalRange: 100, rangeMax: $serializer->getMaxRange());

                for ($i = 0; $i <= 10; $i++) {
                    $length = \strlen($serializer->serialize($i));
                    $this::assertGreaterThanOrEqual($m, $length);
                    $this::assertLessThanOrEqual($m + 2, $length);
                }

                $this->validateOutOfRange($alphabet, $serializer, $serializer->getMaxRange());

                try {
                    $serializer->serialize($serializer->getMaxRange() + 1);
                    $this::fail("Id should be out of range, alphabet {$alphabet}");
                } catch (IdEncodeException $e) {
                }
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        new HashSerializerPHP(minLength: 12);
    }

    private function validateBlocks(SerializerContract $serializer, int $min, int $max): void
    {
        $incrementHashDeserialized = hash_init('sha3-512');
        $incrementHashToSerialize = hash_init('sha3-512');
        $serializedIds = [];
        $expected = 0;

        for ($i = $min; $i < $max; $i++) {
            $id = $serializer->serialize($i);
            $serializedIds[$id] = $id;
            $expected++;
            $deserialized = $serializer->deserialize($id);
            \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
            \hash_update($incrementHashToSerialize, (string) \json_encode($i));
        }
        $this::assertSame(\hash_final($incrementHashToSerialize), \hash_final($incrementHashDeserialized));
        $this::assertCount($expected, $serializedIds);
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashSerializerPHP(alphabet: '');
    }

    public function testEncodeNegative(): void
    {
        $this::expectException(IdEncodeException::class);
        (new HashSerializerPHP())->serialize(-1);
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashSerializerPHP(alphabet: 's');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashSerializerPHP(alphabet: 'gc2bqRf6Q3Mvm4YnWtJy7KPzGTBVwN8XrCjkZ1hxHH');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashSerializerPHP(alphabet: 'gc2bqRf6Q3Mvm4YnWtJy7KPzGTBVwN8XrCjkZ1hxHâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new HashSerializerPHP();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new HashSerializerPHP();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('-');
    }

    public function testBadCharactersInIdMiddle(): void
    {
        $serializer = new HashSerializerPHP();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('Db-Db');
    }

    public function testGetAlphabet(): void
    {
        $serializer = new HashSerializerPHP(Alphabets::CUSTOM_SAFE_ALPHABET);
        $this::assertSame(Alphabets::CUSTOM_SAFE_ALPHABET, $serializer->getAlphabet());
    }

    public function testMaxLength(): void
    {
        $serializer = new HashSerializerPHP();
        $this::assertSame(12, $serializer->getMaxEncodedLength());
    }

    public function testEvenCharsDistributionW1(): void
    {
        foreach ($this->alphabets(9) as $alphabet) {
            $len = strlen($alphabet);
            if (($len & ($len - 1)) === 0 || ($len & 1) === 0) {
                continue;
            }
            $randomBias = 2.5;
            if ($len < 50) {
                $randomBias += (50 - $len) * 0.1;
            }
            $encoder = new HashSerializerPHP(alphabet: $alphabet, minLength: 6);

            $startLinear = pow(\strlen($encoder->getAlphabet()), 6);
            $this->validateCharsDistributionW1($encoder, $startLinear, $startLinear + 1000, 1, false, $randomBias);

            $start = 1 << 16;
            $this->validateCharsDistributionW1($encoder, $start, $start + $start + 1, $start, false, $randomBias);

            $startLinear = $encoder->getOffset(); // defaults for min 6 length
            $this->validateCharsDistributionW1($encoder, $startLinear, $startLinear + 1000, 1, false, $randomBias);

            // linear with first block, it should have distribution near random
            $this->validateCharsDistributionW1($encoder, 0, 65535, 10, false, $randomBias);

            // random
            $this->validateCharsDistributionW1($encoder, 1 << 16, 0xFFFFFFFF, 1, true, $randomBias);
            $this->validateCharsDistributionW1($encoder, 1 << 32, 0xFFFFFFFFFFFF1, 1, true, $randomBias);
            $this->validateCharsDistributionW1($encoder, 1 << 48, $encoder->getMaxRange(), 1, true, $randomBias);
            $this->validateCharsDistributionW1($encoder, 0, $encoder->getMaxRange(), 1, true, $randomBias);
        }
    }

    private function validateCharsDistributionW1(SerializerContract $encoder, int $min, int $max, int $step = 1, bool $random = true, float $lessThanRandomMultiply = 2.5): void
    {
        $total = $random ? 100 : (int) ceil(($max - $min) / $step);

        $ids = new \SplFixedArray($total);
        if ($random) {
            for ($i = 0; $i < $total; $i++) {
                $ids[$i] = $encoder->serialize(\random_int($min, $max));
            }
        } else {
            $counter = 0;
            for ($i = $min; $i < $max; $i += $step) {
                $ids[$counter++] = $encoder->serialize($i);
            }
        }

        $maxDeviations = $this->getMaxDeviation($ids, $encoder->getAlphabet());

        // max deviation 2 times as random one
        $this::assertLessThan(
            $maxDeviations['random'] * $lessThanRandomMultiply,
            $maxDeviations['real'],
            sprintf(
                'Max deviation of %s (%f) is above limit %f, mean %f',
                $maxDeviations['mostFrequentChar'],
                $maxDeviations['real'],
                $maxDeviations['random'] * $lessThanRandomMultiply,
                $maxDeviations['mean'],
            ),
        );
    }

    public function testBackwardCompatibility(): void
    {
        $encoder = new HashSerializerPHP(minLength: 1);
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->serialize($id), 0, PHP_INT_MAX, 'HashSerializerPHP');
    }
}
