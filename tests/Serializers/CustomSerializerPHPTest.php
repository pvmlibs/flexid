<?php

declare(strict_types=1);

namespace Tests\Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Serializers\Implementations\CustomSerializerPHP;
use Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersBCMath;
use Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersGmp;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class CustomSerializerPHPTest extends TestCase
{
    use HasSerializerTesting;
    use HasBackwardCompatibilityTesting;
    use HasCharDistributionTesting;

    public function testSerializeDeserializeWithPositives(): void
    {
        $serializer = new CustomSerializerPHP(null);
        $this->validateSerializeDeserialize($serializer, true);

        foreach ($this->alphabets() as $alphabet) {
            $serializer = new CustomSerializerPHP(alphabet: $alphabet);
            $this->validateSerializeDeserialize(serializer: $serializer, positivesOnly: true, incrementalRange: 100);

            $this->validateOutOfRange($alphabet, $serializer, PHP_INT_MAX);
        }

        $this::expectException(IdEncodeException::class);
        $serializer->serialize(-1);
    }

    public function testSerializeDeserializeFullRange(): void
    {
        $serializer = new CustomSerializerPHP(fullRangeIntegerOperations: new FullRangeIntegersBCMath());
        $this->validateSerializeDeserialize($serializer);
        $bcmathHash = $this->validateSerializeDeserialize($serializer, false, false);

        $serializer = new CustomSerializerPHP(fullRangeIntegerOperations: new FullRangeIntegersGmp());
        $this->validateSerializeDeserialize($serializer);
        $gmpHash = $this->validateSerializeDeserialize($serializer, false, false);
        $this::assertSame($bcmathHash, $gmpHash, 'BCMath and GMP are not interoperable');

        foreach ($this->alphabets() as $alphabet) {
            $serializer = new CustomSerializerPHP(new FullRangeIntegersBCMath(), $alphabet);
            $this->validateSerializeDeserialize(serializer: $serializer, incrementalRange: 100);

            $serializer = new CustomSerializerPHP(new FullRangeIntegersGmp(), $alphabet);
            $this->validateSerializeDeserialize(serializer: $serializer, incrementalRange: 100);

            $this->validateOutOfRange($alphabet, $serializer, -1);
        }
    }

    public function testSerializeDeserializeWithPositivesShortAlphabet(): void
    {
        $serializer = new CustomSerializerPHP(null, 'ab');
        $this->validateSerializeDeserialize($serializer, true);

        $this::expectException(IdEncodeException::class);
        $serializer->serialize(-1);
    }

    public function testSerializeDeserializeFullRangeDifferentAlphabets(): void
    {
        foreach ($this->alphabets() as $alphabet) {
            $serializer = new CustomSerializerPHP(new FullRangeIntegersBCMath(), $alphabet);
            $this->validateSerializeDeserialize(serializer: $serializer, incrementalRange: 1000);

            $serializer = new CustomSerializerPHP(new FullRangeIntegersGmp(), $alphabet);
            $this->validateSerializeDeserialize(serializer: $serializer, incrementalRange: 1000);
        }
    }

    public function testDeserializeNegativeWithPositive(): void
    {
        $serializer = new CustomSerializerPHP(new FullRangeIntegersGmp());
        $serialized = $serializer->serialize(-1);

        $serializerPositives = new CustomSerializerPHP();
        $this::expectException(IdDecodeException::class);
        $serializerPositives->deserialize($serialized);
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomSerializerPHP(alphabet: '');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomSerializerPHP(alphabet: 's');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomSerializerPHP(alphabet: 'ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomSerializerPHP(alphabet: 'sbâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new CustomSerializerPHP();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new CustomSerializerPHP();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('-');
    }

    public function testBadCharactersInIdMiddle(): void
    {
        $serializer = new CustomSerializerPHP();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('Db-Db');
    }

    public function testBackwardCompatibilityPositives(): void
    {
        $serializer = new CustomSerializerPHP();
        $this->validateBackwardCompatibility(fn (int $id): string => $serializer->serialize($id), 0, PHP_INT_MAX, 'CustomAlphabetSerializer_Positives');
    }

    public function testBackwardCompatibilitBcMath(): void
    {
        $serializer = new CustomSerializerPHP(new FullRangeIntegersBCMath());
        $this->validateBackwardCompatibility(fn (int $id): string => $serializer->serialize($id), PHP_INT_MIN, PHP_INT_MAX, 'CustomAlphabetSerializer_BCmath');
    }

    public function testBackwardCompatibilitGmp(): void
    {
        $serializer = new CustomSerializerPHP(new FullRangeIntegersGmp());
        $this->validateBackwardCompatibility(fn (int $id): string => $serializer->serialize($id), PHP_INT_MIN, PHP_INT_MAX, 'CustomAlphabetSerializer_Gmp');
    }
}
