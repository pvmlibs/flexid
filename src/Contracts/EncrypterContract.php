<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

interface EncrypterContract
{
    /**
     * @param string $additionalData When using encryption with authentication, this is used to authenticate id
     *                               id space before encryption. For 64bit integers, this should be 0.
     *
     * @throws IdEncodeException
     */
    public function encrypt(int $id, string $additionalData = ''): string;

    /**
     * @param string $additionalData When using encryption with authentication, this is used to authenticate id
     *                               id space before encryption. For 64bit integers, this should be 0.
     *
     * @throws IdDecodeException|IdDecryptException
     */
    public function decrypt(string $id, string $additionalData = ''): int;

    public function maxOutputLength(): int;
}
