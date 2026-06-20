<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Pvmlibs\FlexId\Concerns\HasIdHandler;
use Pvmlibs\FlexId\Contracts\IdHandlerContract;
use Pvmlibs\FlexId\IdHandlers\EncodedId;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\HashSerializer;
use Pvmlibs\FlexId\Signers\Signer;

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
                    secret: 'rCl29//aZ51LjLQZKUbMUA==',
                    serializer: new BaseSerializer(),
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
