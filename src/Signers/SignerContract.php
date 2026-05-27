<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Signers;

use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;
use Pvmlibs\FlexId\Serializers\SerializerContract;

interface SignerContract
{
    /**
     * @throws IdEncodeException|IdSigningException
     */
    public function getSignedId(string $id): string;

    /**
     * @throws IdVerifySignException
     */
    public function getIdFromSigned(string $idWithSign): string;

    public function getSerializer(): SerializerContract;
}
