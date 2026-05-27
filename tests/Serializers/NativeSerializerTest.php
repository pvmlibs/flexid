<?php

declare(strict_types=1);

namespace Tests\Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\NativeSerializer;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasIdCharDistributionTesting;

/**
 * @internal
 */
final class NativeSerializerTest extends TestCase
{
    use HasSerializerTesting;
    use HasIdCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testSerializeDeserializeWithDefaultAlphabet(): void
    {
        $serializer = new NativeSerializer();
        $this->validateSerializeDeserialize($serializer);
    }

    public function testSerializeDeserializeWithExtendedAlphabet(): void
    {
        $encoder = new NativeSerializer(NativeSerializer::EXTENDED_ALPHABET);
        $this->validateSerializeDeserialize($encoder);
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NativeSerializer('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NativeSerializer('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NativeSerializer('ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NativeSerializer('sbâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new NativeSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new NativeSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('-');
    }

    public function testIsConstantLength(): void
    {
        $serializer = new NativeSerializer();
        $this::assertFalse($serializer->isConstantLength());
    }

    public function testEvenCharsDistribution(): void
    {
        $encoder = new NativeSerializer();

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $id = [
                \random_int(0, 0xFFFF),
                \random_int(0, 0xFFFF),
                \random_int(0, 0xFFFF),
                \random_int(0, 0xFFFF),
            ];

            $ids[$i] = $encoder->serialize($id);
        }

        $maxDeviations = $this->getMaxDeviation($ids, $encoder->getAlphabet());
        // max deviation 5 times as random one
        $this::assertLessThan($maxDeviations['random'] * 5, $maxDeviations['real']);
    }

    public function testBackwardCompatibility(): void
    {
        $encoder = new NativeSerializer();
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->serialize([
            $id,
            $id,
            $id,
            $id,
        ]), 0xFFFF, 'NativeSerializer');
    }
}
