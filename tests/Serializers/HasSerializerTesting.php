<?php

declare(strict_types=1);

namespace Tests\Serializers;

use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

trait HasSerializerTesting
{
    public function validateSerializeDeserialize(SerializerContract $serializer, bool $positivesOnly = false, bool $withRandom = true, int $incrementalRange = 1000, int $rangeMax = PHP_INT_MAX): string
    {
        $serializedIds = [];
        $expected = 0;

        $incrementHashDeserialized = hash_init('sha3-512');
        $incrementHashToSerialize = hash_init('sha3-512');

        for ($i = 1; $i < $incrementalRange; $i++) {
            $id = $serializer->serialize($i);
            $serializedIds[$id] = $id;
            $expected++;
            $deserialized = $serializer->deserialize($id);
            \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
            \hash_update($incrementHashToSerialize, (string) \json_encode($i));

            $id = $serializer->serialize($rangeMax - $i);
            $serializedIds[$id] = $id;
            $expected++;
            $deserialized = $serializer->deserialize($id);
            \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
            \hash_update($incrementHashToSerialize, (string) \json_encode($rangeMax - $i));
        }

        if ($positivesOnly === false) {
            for ($i = -2; $i > -$incrementalRange; $i--) {
                $id = $serializer->serialize($i);
                $serializedIds[$id] = $id;
                $expected++;
                $deserialized = $serializer->deserialize($id);
                \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
                \hash_update($incrementHashToSerialize, (string) \json_encode($i));

                $id = $serializer->serialize(PHP_INT_MIN - $i);
                $serializedIds[$id] = $id;
                $expected++;
                $deserialized = $serializer->deserialize($id);
                \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
                \hash_update($incrementHashToSerialize, (string) \json_encode(PHP_INT_MIN - $i));
            }
        }

        if ($withRandom) {
            for ($i = 0; $i < 1000; $i++) {
                $toSerialize = \rand($incrementalRange, $rangeMax - $incrementalRange);
                $id = $serializer->serialize($toSerialize);
                $serializedIds[$id] = $id;
                $expected++;
                $deserialized = $serializer->deserialize($id);
                \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
                \hash_update($incrementHashToSerialize, (string) \json_encode($toSerialize));
            }

            if ($positivesOnly === false) {
                for ($i = 0; $i < 1000; $i++) {
                    $toSerialize = \rand(PHP_INT_MIN + $incrementalRange, -$incrementalRange);
                    $id = $serializer->serialize($toSerialize);
                    $serializedIds[$id] = $id;
                    $expected++;
                    $deserialized = $serializer->deserialize($id);
                    \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
                    \hash_update($incrementHashToSerialize, (string) \json_encode($toSerialize));
                }
            }
        }

        // corner cases
        $serialized = $serializer->serialize(0);
        $serializedIds[$serialized] = $serialized;
        $expected++;
        $this::assertSame(0, $deserialized = $serializer->deserialize($serialized));
        \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
        \hash_update($incrementHashToSerialize, (string) \json_encode(0));

        $serialized = $serializer->serialize($rangeMax);
        $serializedIds[$serialized] = $serialized;
        $expected++;
        $this::assertSame($rangeMax, $deserialized = $serializer->deserialize($serialized));
        $this::assertLessThanOrEqual($serializer->getMaxEncodedLength(), \strlen($serialized));
        \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
        \hash_update($incrementHashToSerialize, (string) \json_encode($rangeMax));

        if ($positivesOnly === false) {
            $serialized = $serializer->serialize(-1);
            $serializedIds[$serialized] = $serialized;
            $expected++;
            $this::assertSame(-1, $deserialized = $serializer->deserialize($serialized));
            $this::assertSame($serializer->getMaxEncodedLength(), \strlen($serialized));
            \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
            \hash_update($incrementHashToSerialize, (string) \json_encode(-1));

            $serialized = $serializer->serialize(PHP_INT_MIN);
            $serializedIds[$serialized] = $serialized;
            $expected++;
            $this::assertSame(PHP_INT_MIN, $deserialized = $serializer->deserialize($serialized));
            \hash_update($incrementHashDeserialized, (string) \json_encode($deserialized));
            \hash_update($incrementHashToSerialize, (string) \json_encode(PHP_INT_MIN));
        }

        $this::assertSame($finalHashSerialized = \hash_final($incrementHashToSerialize), \hash_final($incrementHashDeserialized));

        // this is more efficient than $this::assertCount, especially with large arrays
        $this::assertSame($expected, count($serializedIds), 'There are duplicates');

        return $finalHashSerialized;
    }

    /**
     * @return iterable<string>
     */
    private function alphabets(int $minLength = 2): iterable
    {
        $alphabet = 'R9jHJSAcLBfFroKqTzXvgxtNbsp83DEkVMIn-y0m5hdG_P47awQl26uYZUeW1iCO';
        for ($i = $minLength; $i <= \strlen($alphabet); $i++) {
            yield \substr($alphabet, 0, $i);
        }
    }

    private function validateOutOfRange(string $alphabet, SerializerContract $serializer, int $maxRange): void
    {
        try {
            // id too long
            $serializer->deserialize($serializer->serialize($maxRange) . $alphabet[0]);
            $this::fail("Id should be out of range, alphabet {$alphabet} {$maxRange} " . $serializer->serialize($maxRange) . $alphabet[0]);
        } catch (IdDecodeException $e) {
        }
        try {
            // id within length but out of range
            if (\strlen($alphabet) > 2) {
                $serializer->deserialize(\str_repeat(\substr($alphabet, 1), $serializer->getMaxEncodedLength()));
                $this::fail("Id should be out of range, alphabet {$alphabet}");
            }
        } catch (IdDecodeException $e) {
        }
    }

    private function runDeterministicBatch(SerializerContract $serializer, bool $positivesOnly = false): string
    {
        $incrementHash = \hash_init('sha3-512');

        // linear
        for ($id = 1; $id < 500; $id++) {
            \hash_update($incrementHash, $serializer->serialize($id));
            \hash_update($incrementHash, $serializer->serialize(PHP_INT_MAX - $id));
        }

        // test random id for rest int range range
        $step = intdiv(PHP_INT_MAX, 500);
        for ($id = 500; $id < PHP_INT_MAX - 500; $id += $step) {
            \hash_update($incrementHash, $serializer->serialize($id));
        }

        // corner cases
        \hash_update($incrementHash, $serializer->serialize(PHP_INT_MAX));
        \hash_update($incrementHash, $serializer->serialize(0));

        if ($positivesOnly === false) {
            // linear
            for ($id = 1; $id < 500; $id++) {
                \hash_update($incrementHash, $serializer->serialize(-$id));
                \hash_update($incrementHash, $serializer->serialize(PHP_INT_MIN + $id));
            }

            // test random id for rest int range range
            $step = intdiv(PHP_INT_MAX, 500);
            for ($id = 500; $id < PHP_INT_MAX - 500; $id += $step) {
                \hash_update($incrementHash, $serializer->serialize(-$id));
            }

            \hash_update($incrementHash, $serializer->serialize(PHP_INT_MIN));
        }

        return \hash_final($incrementHash);
    }
}
