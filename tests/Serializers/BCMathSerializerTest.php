<?php

declare(strict_types=1);

namespace Tests\Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Serializers\GMPSerializer;
use Tests\Internal\HasBackwardCompatibilityTesting;

/**
 * @internal
 */
final class BCMathSerializerTest extends TestCase
{
    use HasSerializerTesting;
    use HasBackwardCompatibilityTesting;

    public function testSerializeDeserializeWithDefaultAlphabet(): void
    {
        $serializer = new BCMathSerializer();
        $this->validateSerializeDeserialize($serializer);
    }

    public function testSerializeDeserializeWithExtendedAlphabet(): void
    {
        $encoder = new BCMathSerializer(BCMathSerializer::EXTENDED_ALPHABET);
        $this->validateSerializeDeserialize($encoder);
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BCMathSerializer('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BCMathSerializer('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BCMathSerializer('ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BCMathSerializer('sbâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new BCMathSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new BCMathSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('-');
    }

    public function testBadCharactersInIdMiddle(): void
    {
        $serializer = new GMPSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('Db-Db');
    }

    public function testIsConstantLength(): void
    {
        $serializer = new BCMathSerializer();
        $this::assertFalse($serializer->isConstantLength());
    }

    public function testBackwardCompatibility(): void
    {
        $serializer = new BCMathSerializer();
        $this->validateBackwardCompatibility(fn (int $id): string => $serializer->serialize([
            $id,
            $id,
            $id,
            $id,
        ]), 0xFFFF, 'BCMathSerializer');
    }
}
