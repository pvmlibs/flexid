<?php

declare(strict_types=1);

namespace Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Serializers\CustomSerializer;

/**
 * @internal
 */
final class CustomSerializerTest extends TestCase
{
    public function testSerializeDeserialize(): void
    {
        // this will use flexid_utils extension if possible
        $serializer = new CustomSerializer();
        $serialized = $serializer->serialize(123456);

        $serializer = new CustomSerializer(forcePHPImplementation: true);
        $serializedPHP = $serializer->serialize(123456);

        $this::assertSame($serialized, $serializedPHP);
    }
}
