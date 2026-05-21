<?php

declare(strict_types=1);

namespace Tests\Encrypters;

use Pvmlibs\FlexId\Encrypters\Serializers\SerializerContract;

trait HasSerializerTesting
{
    public function validateSerializeDeserialize(SerializerContract $serializer): void
    {
        $serializedIds = [];
        for ($i = 0; $i < 0xFFFF; $i += 100) {
            $toSerialize = [
                $i, $i, $i, $i,
            ];
            $serializedIds[] = ($id = $serializer->serialize($toSerialize));
            $this::assertSame($toSerialize, $serializer->deserialize($id));
        }

        $this::assertCount(\count($serializedIds), \array_unique($serializedIds));

        $serializedIds = [];
        for ($i = 0; $i < 10000; $i++) {
            $toSerialize = [
                \rand(0, 0xFFFF), \rand(0, 0xFFFF), \rand(0, 0xFFFF), \rand(0, 0xFFFF),
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
}
