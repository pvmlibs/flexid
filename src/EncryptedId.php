<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId;

use Pvmlibs\FlexId\Contracts\IdGenerator;
use Pvmlibs\FlexId\Encrypters\EncrypterContract;
use Pvmlibs\FlexId\Signers\SignerContract;

class EncryptedId implements IdGenerator
{
    public function __construct(
        private FlexIdGenerator $flexIdGenerator,
        private EncrypterContract $encrypter,
        private ?SignerContract $signer = null,
    ) {
    }

    public function generateId(): int
    {
        return $this->flexIdGenerator->id();
    }

    public function toPublicId(int $id): string
    {
        $encrypted = $this->encrypter->encrypt($id);
        if ($this->signer !== null) {
            return $this->signer->getSignedId($encrypted);
        }

        return $encrypted;
    }

    public function fromPublicId(string $publicId): int
    {
        if ($this->signer !== null) {
            $publicId = $this->signer->getIdFromSigned($publicId);
        }

        return $this->encrypter->decrypt($publicId);
    }
}
