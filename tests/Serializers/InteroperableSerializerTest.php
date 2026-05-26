<?php

declare(strict_types=1);

namespace Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Serializers\BCMathSerializer;
use Tests\Serializers\HasSerializerTesting;

/**
 * @internal
 */
final class InteroperableSerializerTest extends TestCase
{
    use HasSerializerTesting;

    public function testBCMathAndGMInteroperable(): void
    {
        $serializerBC = new BCMathSerializer();
        $serializerGMP = new BCMathSerializer();

        for ($i = 0; $i < 1000; $i++) {
            $toSerialize = [
                \rand(0, 0xFFFF), \rand(0, 0xFFFF), \rand(0, 0xFFFF), \rand(0, 0xFFFF),
            ];
            $this::assertSame($serializerBC->serialize($toSerialize), $serializerGMP->serialize($toSerialize));
        }
    }
}
