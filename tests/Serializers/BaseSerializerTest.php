<?php

declare(strict_types=1);

namespace Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Serializers\BaseSerializer;

/**
 * @internal
 */
final class BaseSerializerTest extends TestCase
{
    public function testSerializeDeserialize(): void
    {
        // this will use flexid_utils extension if possible
        $serializer = new BaseSerializer();
        $serialized = $serializer->serialize(123456);

        $serializer = new BaseSerializer(forcePHPImplementation: true);
        $serializedPHP = $serializer->serialize(123456);

        $this::assertSame($serialized, $serializedPHP);
    }

    public function testInvalidLengthAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializer('abcde');
    }
}
