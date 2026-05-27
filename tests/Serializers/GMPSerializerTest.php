<?php

declare(strict_types=1);

namespace Tests\Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\GMPSerializer;
use Tests\Internal\HasBackwardCompatibilityTesting;

/**
 * @internal
 */
final class GMPSerializerTest extends TestCase
{
    use HasSerializerTesting;
    use HasBackwardCompatibilityTesting;

    public function testSerializeDeserializeWithDefaultAlphabet(): void
    {
        $serializer = new GMPSerializer();
        $this->validateSerializeDeserialize($serializer);
    }

    public function testSerializeDeserializeWithExtendedAlphabet(): void
    {
        $encoder = new GMPSerializer(GMPSerializer::EXTENDED_ALPHABET);
        $this->validateSerializeDeserialize($encoder);
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GMPSerializer('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GMPSerializer('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GMPSerializer('ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GMPSerializer('sbâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new GMPSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new GMPSerializer();
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
        $serializer = new GMPSerializer();
        $this::assertFalse($serializer->isConstantLength());
    }

    public function testGetAlphabet(): void
    {
        $serializer = new GMPSerializer('d7x3Db2LY5Qgv1k9wZFfKMCztc6n');
        $this::assertSame('d7x3Db2LY5Qgv1k9wZFfKMCztc6n', $serializer->getAlphabet());
    }

    public function testBackwardCompatibility(): void
    {
        $encoder = new GMPSerializer();
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->serialize([
            $id,
            $id,
            $id,
            $id,
        ]), 0xFFFF, 'GMPSerializer');
    }
}
