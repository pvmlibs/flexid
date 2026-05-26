<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId;

use Pvmlibs\FlexId\Contracts\IdGenerator;
use Pvmlibs\FlexId\Encoders\EncoderContract;
use Pvmlibs\FlexId\Signers\SignerContract;

class EncodedId implements IdGenerator
{
    public function __construct(
        private FlexIdGenerator $flexIdGenerator,
        private EncoderContract $encoder,
        private ?SignerContract $signer = null,
    ) {
    }

    public function generateId(): int
    {
        return $this->flexIdGenerator->id();
    }

    public function toPublicId(int $id): string
    {
        $encoded = $this->encoder->encode($id);
        if ($this->signer !== null) {
            return $this->signer->getSignedId($encoded);
        }

        return $encoded;
    }

    public function fromPublicId(string $publicId): int
    {
        if ($this->signer !== null) {
            $publicId = $this->signer->getIdFromSigned($publicId);
        }

        return $this->encoder->decode($publicId);
    }
}
