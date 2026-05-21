<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encoders;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

interface EncoderContract
{
    /**
     * @throws IdEncodeException
     */
    public function encode(int $id): string;

    /**
     * @throws IdDecodeException
     */
    public function decode(string $id): int;

    public function getMaxEncodedLength(): int;

    public function getAlphabet(): string;
}
