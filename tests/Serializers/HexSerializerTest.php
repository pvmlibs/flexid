<?php

declare(strict_types=1);

namespace Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\HexSerializer;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;
use Tests\Serializers\HasSerializerTesting;

/**
 * @internal
 */
final class HexSerializerTest extends TestCase
{
    use HasSerializerTesting;
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testSerializeDeserializeWithDefaultAlphabet(): void
    {
        $serializer = new HexSerializer();
        $this->validateSerializeDeserialize($serializer);
    }

    public function testEmptyId(): void
    {
        $serializer = new HexSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new HexSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('0123456789abcref');
    }

    public function testEvenCharsDistribution(): void
    {
        $encoder = new HexSerializer();

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encoder->serialize(random_int(PHP_INT_MIN, PHP_INT_MAX));
        }
        $maxDeviations = $this->getMaxDeviation($ids, $encoder->getAlphabet());
        // max deviation 2 times as random one from mean
        $this::assertLessThan($maxDeviations['random'] * 2, $maxDeviations['real']);
    }

    public function testBackwardCompatibility(): void
    {
        $encoder = new HexSerializer();
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->serialize($id), PHP_INT_MIN, PHP_INT_MAX, 'HexSerializer');
    }
}
