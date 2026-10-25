<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

interface EncrypterContract
{
    /**
     * @throws IdEncodeException
     */
    public function encrypt(int $id): string;

    /**
     * @throws IdDecodeException|IdDecryptException
     */
    public function decrypt(string $id): int;

    public function maxOutputLength(): int;
}
