<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;

interface IdHandlerContract
{
    public function generateId(): int;

    /**
     * @throws IdEncodeException|IdSigningException
     */
    public function toPublicId(int $id, string $additionalData = ''): string;

    /**
     * @throws IdDecodeException|IdDecryptException|IdBadSignException|IdVerifySignException
     */
    public function fromPublicId(string $publicId, string $additionalData = ''): int;
}
