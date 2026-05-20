<?php

declare(strict_types=1);

namespace Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * @internal
 */
final class BCMathSerializerTest extends TestCase
{
    public function testSerializeDeserialize(): void
    {
        $serializer = new BCMathSerializer();

        $serializedIds = [];
        for ($i = 0; $i < 0xFFFF; $i += 1000) {
            $toSerialize = [
                $i, $i, $i, $i,
            ];
            $serializedIds[] = ($id = $serializer->serialize($toSerialize));
            $this::assertSame($toSerialize, $serializer->deserialize($id));
        }

        $this::assertCount(\count($serializedIds), \array_unique($serializedIds));

        $serializedIds = [];
        for ($i = 0; $i < 1000; $i++) {
            $toSerialize = [
                \rand($i, 0xFFFF), \rand($i, 0xFFFF), \rand($i, 0xFFFF), \rand($i, 0xFFFF),
            ];
            $serializedIds[] = ($id = $serializer->serialize($toSerialize));
            $this::assertSame($toSerialize, $serializer->deserialize($id));
        }

        $this::assertCount(\count($serializedIds), \array_unique($serializedIds));

        $maxId = [
            0xFFFF, 0xFFFF, 0xFFFF, 0xFFFF,
        ];
        $encoded = $serializer->serialize($maxId);
        $this::assertSame($maxId, $serializer->deserialize($encoded));
        $this::assertSame(\strlen($encoded), $serializer->getMaxEncodedLength());
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
