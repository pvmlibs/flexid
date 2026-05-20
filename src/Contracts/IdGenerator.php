<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

interface IdGenerator
{
    public function generateId(): int;

    /**
     * @throws IdEncodeException
     */
    public function toPublicId(int $id): string;

    /**
     * @throws IdDecodeException
     */
    public function fromPublicId(string $publicId): int;
}
