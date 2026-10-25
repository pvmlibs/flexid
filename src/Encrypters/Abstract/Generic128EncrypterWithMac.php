<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters\Abstract;

use Pvmlibs\FlexId\Contracts\Cipher128Contract;
use Pvmlibs\FlexId\Contracts\EncrypterWithMacContract;
use Pvmlibs\FlexId\Contracts\HMACContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

class Generic128EncrypterWithMac implements EncrypterWithMacContract
{
    protected int $maxOutputChars;

    public function __construct(
        private Cipher128Contract $cipher,
        private HMACContract $hmac,
        private SerializerContract $serializer,
        public string $separator = '.',
    ) {
        if (\strlen($this->separator) !== 1) {
            throw new \InvalidArgumentException('Sign separator must be a single character');
        }

        if (str_contains($this->serializer->getAlphabet(), $this->separator)) {
            throw new \InvalidArgumentException(\sprintf('Signer serializer cannot include separator char %s in alphabet', $this->separator));
        }
        $this->maxOutputChars = $this->serializer->getMaxEncodedLength() * 2;

        $this->hmac->setup(1);
    }

    public function encrypt(int $id, string $additionalData = ''): string
    {
        $sign = $this->hmac->sign($additionalData, $id);

        $encrypted = $this->cipher->encrypt($id, $sign[0]);

        $part1 = $this->serializer->serialize($encrypted[0]);
        $part2 = $this->serializer->serialize($encrypted[1]);

        if (\strlen($part1) < $this->serializer->getMaxEncodedLength()) {
            $part1 .= $this->separator;
        }

        return $part1 . $part2;
    }

    public function decrypt(string $id, string $additionalData = ''): int
    {
        $idEncodedLength = \strlen($id);
        if ($idEncodedLength > $this->maxOutputChars || $idEncodedLength === 0) {
            throw new IdDecodeException('Wrong input length');
        }

        $maxPartLength = $this->serializer->getMaxEncodedLength();

        $separatorPos = \strrpos($id, $this->separator);

        if ($separatorPos === false) {
            $idEncodedPart1 = \substr($id, 0, $maxPartLength);
            $idEncodedPart2 = \substr($id, $maxPartLength);
        } else {
            $idEncodedPart1 = \substr($id, 0, $separatorPos);
            $idEncodedPart2 = \substr($id, $separatorPos + 1);
        }

        $idEncodedPart1 = $this->serializer->deserialize($idEncodedPart1);
        $idEncodedPart2 = $this->serializer->deserialize($idEncodedPart2);

        $decrypted = $this->cipher->decrypt($idEncodedPart1, $idEncodedPart2);

        $sign = $this->hmac->sign($additionalData, $decrypted[0]);

        if ($sign[0] === $decrypted[1]) {
            return $decrypted[0];
        }

        throw new IdBadSignException();
    }

    public function maxOutputLength(): int
    {
        return $this->maxOutputChars;
    }
}
