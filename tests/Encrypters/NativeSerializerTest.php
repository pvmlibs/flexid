<?php

declare(strict_types=1);

namespace Tests\Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Encrypters\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * @internal
 */
final class NativeSerializerTest extends TestCase
{
    use HasSerializerTesting;

    public function testSerializeDeserializeWithDefaultAlphabet(): void
    {
        $serializer = new NativeSerializer();
        $this->validateSerializeDeserialize($serializer);
    }

    public function testSerializeDeserializeWithExtendedAlphabet(): void
    {
        $encoder = new NativeSerializer(BCMathSerializer::EXTENDED_ALPHABET);
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
}
