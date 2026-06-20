<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Pvmlibs\FlexId\Concerns\HasIdHandler;
use Pvmlibs\FlexId\Contracts\IdHandlerContract;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\IdHandlers\EncryptedId;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Signers\Signer;

/**
 * @internal
 */
abstract class DummyBaseEncryptedId
{
    use HasIdHandler;
    /**
     * @return list<IdHandlerContract>
     */
    protected static function getIdHandler(): array
    {
        static $class = null;
        $class ??= [
            new EncryptedId(
                new Sparx64Encrypter(
                    'TKXoZyARtcsFzsyQTrfOQw==',
                    new BaseSerializer(),
                ),
                new Signer(
                    'rCl29//aZ51LjLQZKUbMUA==',
                    new BaseSerializer(),
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
