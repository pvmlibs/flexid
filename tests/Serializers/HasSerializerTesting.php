<?php

declare(strict_types=1);

namespace Tests\Serializers;

use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Serializers\SerializerContract;

trait HasSerializerTesting
{
    public function validateSerializeDeserialize(SerializerContract $serializer): void
    {
        $serializedIds = [];
        // use increment hash for testing large range serialized and deserialized values as this is much faster than thousands of asserts
        $incrementHashDeserialized = hash_init('sha256');
        $incrementHashToSerialize = hash_init('sha256');

        for ($i = 0; $i < 0xFFFF; $i++) {
            $toSerialize = [
                $i, 0, 0, 0,
            ];
            $serializedIds[] = ($id = $serializer->serialize($toSerialize));
            $deserialized = $serializer->deserialize($id);
            \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
            \hash_update($incrementHashToSerialize, (string) \json_encode($toSerialize));
        }

        $this::assertSame(\hash_final($incrementHashToSerialize), \hash_final($incrementHashDeserialized));

        $this::assertCount(\count($serializedIds), \array_unique($serializedIds));

        $serializedIds = [];

        $incrementHashDeserialized = hash_init('sha256');
        $incrementHashToSerialize = hash_init('sha256');
        for ($i = 0; $i < 10000; $i++) {
            $toSerialize = [
                \rand(0, 0xFFFF), \rand(0, 0xFFFF), \rand(0, 0xFFFF), \rand(0, 0xFFFF),
            ];
            $serializedIds[] = ($id = $serializer->serialize($toSerialize));
            $deserialized = $serializer->deserialize($id);
            \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
            \hash_update($incrementHashToSerialize, (string) \json_encode($toSerialize));
        }

        $this::assertSame(\hash_final($incrementHashToSerialize), \hash_final($incrementHashDeserialized));

        $this::assertCount(\count($serializedIds), \array_unique($serializedIds));

        $maxId = [
            0xFFFF, 0xFFFF, 0xFFFF, 0xFFFF,
        ];
        $encoded = $serializer->serialize($maxId);
        $this::assertSame($maxId, $serializer->deserialize($encoded));
        $this::assertSame(\strlen($encoded), $serializer->getMaxEncodedLength());

        $this::expectException(IdEncodeException::class);
        $serializer->serialize([0xFFFF + 1, 0, 0, 0]);
    }
}
