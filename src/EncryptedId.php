<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId;

use Pvmlibs\FlexId\Contracts\IdGenerator;
use Pvmlibs\FlexId\Encrypters\EncrypterContract;

class EncryptedId implements IdGenerator
{
    public function __construct(
        private FlexIdGenerator $flexIdGenerator,
        private EncrypterContract $encrypter,
    ) {
    }

    public function generateId(): int
    {
        return $this->flexIdGenerator->id();
    }

    public function toPublicId(int $id): string
    {
        return $this->encrypter->encrypt($id);
    }

    public function fromPublicId(string $publicId): int
    {
        return $this->encrypter->decrypt($publicId);
    }
}
