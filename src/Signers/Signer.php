<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Signers;

use Pvmlibs\FlexId\Contracts\HMACContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Contracts\SignerContract;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;

/**
 * Customizable signer for ensuring id integrity and authenticity by implementing MAC (Message Authentication Code).
 * Can use 64-256 bits hash. Make sure the settings meet your application security needs.
 */
class Signer implements SignerContract
{
    private int $signParts;
    private int $signPartMaxLength;

    /**
     * @param HMACContract $hmac              HMAC algorithm for signing
     * @param string       $separator         char to separate sign from signed data (or other sign parts), added only when calculated
     *                                        sign is smaller than maxSignLength
     * @param int|null     $maxSignLength     defines max sign part length. Sign part is 64-bit chunk of encoded data. When null
     *                                        then it equals max length from serializer.
     * @param int          $signBits          how many bits in total use for the sign (64, 128, 192, 256), it will be serialized in
     *                                        parts of 64 bits, optionally separated by separator
     * @param bool         $onlyPositiveRange optimization for shorter signs, uses 63 from 64 bits of sign part so serializer can work
     *                                        on positives number only. Better for performance in CustomSerializer.
     */
    public function __construct(
        private SerializerContract $serializer,
        private HMACContract $hmac,
        private string $separator = '.',
        ?int $maxSignLength = null,
        private int $signBits = 64,
        private bool $onlyPositiveRange = false,
    ) {
        if (\in_array($this->signBits, [64, 128, 192, 256], true) === false) {
            throw new \InvalidArgumentException('Allowed values for signBits are 64, 128, 192, 256');
        }

        $this->signParts = $this->signBits >> 6;

        if (\strlen($this->separator) !== 1) {
            throw new \InvalidArgumentException('Sign separator must be a single character');
        }

        if (str_contains($this->serializer->getAlphabet(), $this->separator)) {
            throw new \InvalidArgumentException(sprintf('Signer serializer cannot include separator char %s in alphabet', $this->separator));
        }

        $serializerMaxOutput = $this->serializer->getMaxEncodedLength();

        if ($maxSignLength === null) {
            $this->signPartMaxLength = $serializerMaxOutput;
        } else {
            if ($maxSignLength < 1 || $maxSignLength > $serializerMaxOutput) {
                throw new \InvalidArgumentException("Sign length is invalid. Ensure it is between 1 - {$serializerMaxOutput}");
            }
            $this->signPartMaxLength = $maxSignLength;
        }

        $this->hmac->setup($this->signParts);
    }

    public function getSignedId(string $id, string $additionalData = ''): string
    {
        return $id . $this->sign($id, $additionalData);
    }

    /**
     * @throws IdSigningException
     */
    private function sign(string $data, string $additionalData = ''): string
    {
        if ($data === '') {
            throw new IdSigningException('Empty id to sign');
        }

        try {
            $signData = $this->hmac->sign($data . $additionalData);
        } catch (\Exception $e) {
            throw new IdSigningException(message: $e->getMessage(), previous: $e);
        }

        $result = '';
        $parts = 0;
        foreach ($signData as $p) {
            if ($this->onlyPositiveRange) {
                $p &= 0x7FFFFFFFFFFFFFFF; // ~(1 << 63)
            }

            try {
                $sign = $this->serializer->serialize($p);
            } catch (\Exception $e) {
                throw new IdSigningException(message: $e->getMessage(), previous: $e);
            }

            if (\strlen($sign) === $this->signPartMaxLength) {
                $result .= $sign;
                continue;
            }
            if (\strlen($sign) < $this->signPartMaxLength) {
                $result .= $this->separator;
            } else {
                $sign = \substr($sign, 0, $this->signPartMaxLength);
            }

            $result .= $sign;
            $parts++;
            if ($parts >= $this->signParts) {
                break;
            }
        }

        return $result;
    }

    public function getIdFromSigned(string $idWithSign, string $additionalData = ''): string
    {
        if (\strlen($idWithSign) > 64 * 5) { // 64 max part size, 4 sign parts max + 1 part for id
            throw new IdVerifySignException('Signed id is too long');
        }

        $signMaxLength = $this->signParts * $this->signPartMaxLength;
        // search for separator (last occurrence)
        $separatorPos = strrpos($idWithSign, $this->separator);

        if ($separatorPos === false || $separatorPos < (\strlen($idWithSign) - $signMaxLength)) {
            $separatorPos = \strlen($idWithSign) - $signMaxLength;
        } else {
            $endOffset = \strlen($idWithSign) - $separatorPos;
            // calculate current sign part
            $startingPart = \intdiv($endOffset - 1, $this->signPartMaxLength) + 1;

            // process sign parts step by step as they can have variable lengths
            for ($i = $startingPart; $i < $this->signParts; $i++) {
                // move offset to search next separator
                $endOffset = \strlen($idWithSign) - $separatorPos + 1;
                $nextSeparatorPos = \strrpos($idWithSign, $this->separator, -$endOffset);

                if ($nextSeparatorPos === false) {
                    // not found - can assume full sign part length for the rest of parts
                    $separatorPos -= ($this->signPartMaxLength * ($this->signParts - $i));
                    break;
                }
                if ($nextSeparatorPos <= ($maxPartPos = $separatorPos - $this->signPartMaxLength)) {
                    // separator found but is further than sign part max length
                    $separatorPos = $maxPartPos;
                    continue;
                }
                // separator found and is within sign part length, set as current position
                $separatorPos = $nextSeparatorPos;
            }
        }

        $separatorPos = max(0, $separatorPos);

        $id = \substr($idWithSign, 0, $separatorPos);
        $signPart = \substr($idWithSign, $separatorPos);

        try {
            if (hash_equals($this->sign($id, $additionalData), $signPart) === false) {
                // for handling key rotation
                throw new IdBadSignException('Invalid signature');
            }
        } catch (IdSigningException $ex) {
            throw new IdVerifySignException($ex->getMessage());
        }

        return $id;
    }

    public function getAlphabet(): string
    {
        return $this->serializer->getAlphabet();
    }

    public function maxOutputLength(): int
    {
        return $this->signParts * $this->signPartMaxLength;
    }
}
