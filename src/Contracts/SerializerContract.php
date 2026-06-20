<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Transforms 64bit data to/from string.
 */
interface SerializerContract
{
    /**
     * @throws IdEncodeException
     */
    public function serialize(int $data): string;

    /**
     * @param string $data Serialized data
     *
     * @throws IdDecodeException
     */
    public function deserialize(string $data): int;

    public function getMaxEncodedLength(): int;

    public function getAlphabet(): string;
}
