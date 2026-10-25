<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Pvmlibs\FlexId\Concerns\HasIdHandler;
use Pvmlibs\FlexId\Contracts\IdHandlerContract;
use Pvmlibs\FlexId\HMAC\Sha256HMAC;
use Pvmlibs\FlexId\IdHandlers\EncodedId;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\HashSerializer;
use Pvmlibs\FlexId\Signers\Signer;
use Pvmlibs\FlexId\Tools\KeyGen;

/**
 * @internal
 */
abstract class DummyBaseEncodedId
{
    use HasIdHandler;
    /**
     * @return list<IdHandlerContract>
     */
    protected static function getIdHandler(): array
    {
        static $class = null;
        $class ??= [
            new EncodedId(
                new HashSerializer(),
                new Signer(
                    serializer: new BaseSerializer(),
                    hmac: new Sha256HMAC(KeyGen::generate()),
                ),
            ),
        ];

        return $class;
    }

    public function __toString(): string
    {
        return (string) $this->internalId;
    }

    protected static function handleFromPublicIdException(\Exception $exception): never
    {
        throw new DummyException();
    }
}
