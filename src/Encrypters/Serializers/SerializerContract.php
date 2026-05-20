<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

interface SerializerContract
{
    /**
     * @param list<int> $data 64bit integer represented as 4 16bit values as PHP does not support unsigned 64bit integer
     *
     * @throws IdEncodeException
     */
    public function serialize(array $data): string;

    /**
     * @param string $data Serialized data
     *
     * @return list<int> 64bit integer represented as 4 16bit values as PHP does not support unsigned 64bit integer
     *
     * @throws IdDecodeException
     */
    public function deserialize(string $data): array;

    public function getMaxEncodedLength(): int;
}
