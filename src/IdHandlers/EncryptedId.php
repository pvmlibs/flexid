<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\IdHandlers;

use Pvmlibs\FlexId\Contracts\EncrypterContract;
use Pvmlibs\FlexId\Contracts\EncrypterWithMacContract;
use Pvmlibs\FlexId\Contracts\IdGeneratorContract;
use Pvmlibs\FlexId\Contracts\IdHandlerContract;
use Pvmlibs\FlexId\Contracts\SignerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

class EncryptedId implements IdHandlerContract
{
    private int $maxIdLength;

    public function __construct(
        private EncrypterContract|EncrypterWithMacContract $encrypter,
        private ?SignerContract $signer = null,
        private ?IdGeneratorContract $generator = null,
    ) {
        $this->maxIdLength = $this->encrypter->maxOutputLength() + ($this->signer?->maxOutputLength() ?? 0);
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
        if ($this->encrypter instanceof EncrypterContract) {
            $encrypted = $this->encrypter->encrypt($id);
        } else {
            $encrypted = $this->encrypter->encrypt($id, $additionalData);
        }

        if ($this->signer !== null) {
            return $this->signer->getSignedId($encrypted, $additionalData);
        }

        return $encrypted;
    }

    public function fromPublicId(string $publicId, string $additionalData = ''): int
    {
        if (\strlen($publicId) > $this->maxIdLength) {
            throw new IdDecodeException(\sprintf('ID is too long (%d characters)', \strlen($publicId)));
        }

        if ($this->signer !== null) {
            $publicId = $this->signer->getIdFromSigned($publicId, $additionalData);
        }

        if ($this->encrypter instanceof EncrypterContract) {
            return $this->encrypter->decrypt($publicId);
        }

        return $this->encrypter->decrypt($publicId, $additionalData);

    }
}
