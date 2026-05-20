<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

interface EncrypterContract
{
    /**
     * @throws IdEncodeException
     */
    public function encrypt(int $id): string;

    /**
     * @throws IdDecodeException
     */
    public function decrypt(string $id): int;

    public function getMaxEncryptedLength(): int;
}
