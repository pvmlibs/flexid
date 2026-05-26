<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Transforms 64bit data to/from string.
 */
interface SerializerContract
{
    /**
     * @param list<int> $data 64bit integer represented as 4 16bit values as PHP does not support unsigned 64bit integer.
     *                        Lowest word first.
     *
     * @throws IdEncodeException
     */
    public function serialize(array $data): string;

    /**
     * @param string $data Serialized data
     *
     * @return list<int> 64bit integer represented as 4 16bit values as PHP does not support unsigned 64bit integer.
     *                   Lowest word first.
     *
     * @throws IdDecodeException
     */
    public function deserialize(string $data): array;

    public function getMaxEncodedLength(): int;

    public function getAlphabet(): string;

    public function isConstantLength(): bool;
}
