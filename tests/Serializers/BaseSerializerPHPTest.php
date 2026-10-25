<?php

declare(strict_types=1);

namespace Tests\Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\Implementations\BaseSerializerPHP;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class BaseSerializerPHPTest extends TestCase
{
    use HasSerializerTesting;
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testSerializeDeserialize(): void
    {
        $serializer = new BaseSerializerPHP();
        $this->validateSerializeDeserialize($serializer);

        foreach ($this->alphabets() as $alphabet) {
            if ((\strlen($alphabet) & (\strlen($alphabet) - 1)) === 0) { // only power of 2 for now
                $serializer = new BaseSerializerPHP($alphabet);
                $this->validateSerializeDeserialize(serializer: $serializer, incrementalRange: 100, withRandom: false);

                $this->validateOutOfRange($alphabet, $serializer, -1);
            }
        }
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializerPHP('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializerPHP('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializerPHP('FDxkwdMKQRGgCBPLpYmvVJyXZbjczWqq');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializerPHP('FDxkwdMKQRGgCBPLpYmvVJyXZbjczWqqâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new BaseSerializerPHP();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new BaseSerializerPHP();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('-');
    }

    public function testEvenCharsDistribution(): void
    {
        $encoder = new BaseSerializerPHP();

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encoder->serialize(\random_int(PHP_INT_MIN, PHP_INT_MAX));
        }

        $maxDeviations = $this->getMaxDeviation($ids, $encoder->getAlphabet());
        // max deviation 5 times as random one
        $this::assertLessThan($maxDeviations['random'] * 5, $maxDeviations['real']);
    }

    public function testBackwardCompatibility(): void
    {
        $encoder = new BaseSerializerPHP();
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->serialize($id), PHP_INT_MIN, PHP_INT_MAX, 'BaseSerializerPHP');
    }
}
