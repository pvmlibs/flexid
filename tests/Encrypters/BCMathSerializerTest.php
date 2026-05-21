<?php

declare(strict_types=1);

namespace Tests\Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * @internal
 */
final class BCMathSerializerTest extends TestCase
{
    use HasSerializerTesting;

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
}
