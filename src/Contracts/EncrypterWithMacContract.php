<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Contract for encryptors using internally MAC.
 */
interface EncrypterWithMacContract
{
    /**
     * @param string $additionalData This is used for id authentication while decrypting
     *
     * @throws IdEncodeException
     */
    public function encrypt(int $id, string $additionalData = ''): string;

    /**
     * @param string $additionalData Used for id authentication
     *
     * @throws IdDecodeException|IdDecryptException
     */
    public function decrypt(string $id, string $additionalData = ''): int;

    public function maxOutputLength(): int;
}
