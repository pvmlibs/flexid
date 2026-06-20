<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;

interface SignerContract
{
    /**
     * Returns id with sign.
     *
     * @throws IdEncodeException|IdSigningException
     */
    public function getSignedId(string $id, string $additionalData = ''): string;

    /**
     * Verifies id with sign and returns id part.
     *
     * @throws IdVerifySignException|IdBadSignException
     */
    public function getIdFromSigned(string $idWithSign, string $additionalData = ''): string;

    public function getAlphabet(): string;

    public function maxOutputLength(): int;
}
