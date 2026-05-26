<?php

declare(strict_types=1);

namespace Tests\Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\FixedLengthSerializer;

/**
 * @internal
 */
final class FixedLengthSerializerTest extends TestCase
{
    use HasSerializerTesting;

    public function testSerializeDeserializeWithDefaultAlphabet(): void
    {
        $serializer = new FixedLengthSerializer();
        $this->validateSerializeDeserialize($serializer);
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedLengthSerializer('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedLengthSerializer('QFCgDRwkBxMLGKdd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedLengthSerializer('QFCgDRwkBxMLGKdâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new FixedLengthSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new FixedLengthSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('-');
    }

    public function testIsConstantLength(): void
    {
        $serializer = new FixedLengthSerializer();
        $this::assertTrue($serializer->isConstantLength());
    }
}
