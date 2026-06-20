<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\IdHandlers;

use Pvmlibs\FlexId\Contracts\IdGeneratorContract;
use Pvmlibs\FlexId\Contracts\IdHandlerContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Contracts\SignerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

class EncodedId implements IdHandlerContract
{
    private int $maxIdLength;

    public function __construct(
        private SerializerContract $serializer,
        private ?SignerContract $signer = null,
        private ?IdGeneratorContract $generator = null,
    ) {
        $this->maxIdLength = $this->serializer->getMaxEncodedLength() + ($this->signer?->maxOutputLength() ?? 0);
    }

    public function generateId(): int
    {
        if ($this->generator !== null) {
            return $this->generator->id();
        }

        throw new \RuntimeException('Generator is not set.');
    }

    public function toPublicId(int $id, string $additionalData = ''): string
    {
        $encoded = $this->serializer->serialize($id);
        if ($this->signer !== null) {
            return $this->signer->getSignedId($encoded, $additionalData);
        }

        return $encoded;
    }

    public function fromPublicId(string $publicId, string $additionalData = ''): int
    {
        if (\strlen($publicId) > $this->maxIdLength) {
            throw new IdDecodeException(\sprintf('ID is too long (%d characters)', \strlen($publicId)));
        }

        if ($this->signer !== null) {
            $publicId = $this->signer->getIdFromSigned($publicId, $additionalData);
        }

        return $this->serializer->deserialize($publicId);
    }
}
