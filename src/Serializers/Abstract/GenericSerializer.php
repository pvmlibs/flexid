<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers\Abstract;

use Pvmlibs\FlexId\Contracts\SerializerContract;

class GenericSerializer implements SerializerContract
{
    public function __construct(
        private SerializerContract $serializer,
    ) {
    }

    public function serialize(int $data): string
    {
        return $this->serializer->serialize($data);
    }

    public function deserialize(string $data): int
    {
        return $this->serializer->deserialize($data);
    }

    public function getMaxEncodedLength(): int
    {
        return $this->serializer->getMaxEncodedLength();
    }

    public function getAlphabet(): string
    {
        return $this->serializer->getAlphabet();
    }
}
