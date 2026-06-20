<?php

declare(strict_types=1);

namespace Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\Base64Serializer;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;
use Tests\Serializers\HasSerializerTesting;

/**
 * @internal
 */
final class Base64SerializerTest extends TestCase
{
    use HasSerializerTesting;
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testSerializeDeserializeWithDefaultAlphabet(): void
    {
        $serializer = new Base64Serializer();
        $this->validateSerializeDeserialize($serializer);
    }

    public function testEmptyId(): void
    {
        $serializer = new Base64Serializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new Base64Serializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('0123456789abcref.');
    }

    public function testEvenCharsDistribution(): void
    {
        $serializer = new Base64Serializer();

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $serializer->serialize(random_int(PHP_INT_MIN, PHP_INT_MAX));
        }
        $maxDeviations = $this->getMaxDeviation($ids, $serializer->getAlphabet());
        // max deviation 2 times as random one from mean
        $this::assertLessThan(
            $maxDeviations['random'] * 4,
            $maxDeviations['real'],
            sprintf(
                'Max deviation of %s (%f) is above limit %f, mean %f',
                $maxDeviations['mostFrequentChar'],
                $maxDeviations['real'],
                $maxDeviations['random'] * 4,
                $maxDeviations['mean'],
            ),
        );
    }

    public function testBackwardCompatibility(): void
    {
        $encoder = new Base64Serializer();
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->serialize($id), PHP_INT_MIN, PHP_INT_MAX, 'Base64Serializer');
    }
}
