<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Concerns;

use Pvmlibs\FlexId\Contracts\IdHandlerContract;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;

trait HasIdHandler
{
    protected string $publicId = '';

    final public function __construct(
        protected int $internalId,
    ) {
        if ($internalId < 0) {
            throw new \RuntimeException('Negative ID are not supported');
        }
    }

    final public static function fromPublicId(string $publicId): static
    {
        $internalId = $exception = null;

        foreach (static::getIdHandler() as $handler) {
            try {
                $id = $handler->fromPublicId(
                    publicId: $publicId,
                    additionalData: static::getSignData(),
                );
                $internalId = $id - static::idOffset();

                if ($internalId < 0 || is_float($internalId)) { // @phpstan-ignore function.impossibleType
                    $internalId = null;
                    throw new IdDecryptException('ID is out of valid range');
                }
                break;
            } catch (IdBadSignException|IdDecryptException|IdDecodeException $exception) {
                // id could be created wit previous key or serializer has changed, try next handler
                continue;
            } catch (IdVerifySignException $exception) {
                // id format is wrong
                break;
            }
        }

        if ($internalId === null) {
            if ($exception !== null) {
                static::handleFromPublicIdException($exception);
            }
            throw new \RuntimeException('No id handler defined');
        }

        $class = new static($internalId);
        $class->publicId = $publicId;

        return $class;
    }

    public function equals(?self $other): bool
    {
        return $this->internalId === $other?->internalId && $this::idUniqueTypeName() === $other::idUniqueTypeName();
    }

    public function jsonSerialize(): string
    {
        return $this->getPublicId();
    }

    final public function getInternalId(): int
    {
        return $this->internalId;
    }

    final public function getPublicId(): string
    {
        if ($this->publicId === '') {

            $id = $this->internalId + static::idOffset();
            if (is_float($id)) { // @phpstan-ignore function.impossibleType
                throw new IdEncodeException('ID is out of valid range');
            }
            $this->publicId = static::getIdHandler()[0]->toPublicId(
                id: $id,
                additionalData: static::getSignData(),
            );
        }

        return $this->publicId;
    }

    abstract protected static function handleFromPublicIdException(\Exception $exception): never;

    /**
     * Provide list of IdHandlerContract classes. On public decode error it will try with next from the list.
     * If all return error, handleFromPublicIdException() will be called.
     *
     * @return list<IdHandlerContract>
     */
    abstract protected static function getIdHandler(): array;

    /**
     * Define unique id type name. This allows for verifying incoming public id type.
     * If this string is not globally unique within id types, the same public id could be used
     * across different other id types (e.g. id from user table allowed in orders table).
     *
     * @return non-empty-string
     */
    abstract protected static function idUniqueTypeName(): string;

    /**
     * Defines id offset, can be negative or positive.
     */
    protected static function idOffset(): int
    {
        return 0;
    }

    /**
     * Data added for signing, apart from id.
     */
    protected static function getSignData(): string
    {
        return static::idUniqueTypeName() . static::idOffset();
    }
}
