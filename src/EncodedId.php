<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId;

use Pvmlibs\FlexId\Contracts\IdGenerator;
use Pvmlibs\FlexId\Encoders\EncoderContract;

class EncodedId implements IdGenerator
{
    public function __construct(
        private FlexIdGenerator $flexIdGenerator,
        private EncoderContract $encoder,
    ) {
    }

    public function generateId(): int
    {
        return $this->flexIdGenerator->id();
    }

    public function toPublicId(int $id): string
    {
        return $this->encoder->encode($id);
    }

    public function fromPublicId(string $publicId): int
    {
        return $this->encoder->decode($publicId);
    }
}
