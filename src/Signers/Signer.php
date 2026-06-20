<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Signers;

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
    /**
     * @var \Closure(string): string
     */
    private \Closure $signFn;

    private int $signParts;
    private int $signPartMaxLength;

    /**
     * @param string   $secret            Secret key used in MAC. This needs to be treated with high confidentiality, do not
     *                                    include it in source code. Can use generateKey() method to produce the key. In case of
     *                                    non-cryptographic algorithms, key is concatenated to id (less secure).
     *                                    Do not use the same key when encrypting.
     * @param string   $hashAlgo          hash algorithm, recommended ones in order (for security/speed ratio): siphash-2-4, blake2b, sha256
     * @param string   $separator         char to separate sign from signed data (or other sign parts), added only when calculated
     *                                    sign is smaller than maxSignLength
     * @param int|null $maxSignLength     defines max sign part length. Sign part is 64-bit chunk of encoded data. When null
     *                                    then it equals max length from serializer.
     * @param int      $signBits          how many bits in total use for the sign (64, 128, 192, 256), it will be serialized in
     *                                    parts of 64 bits, optionally separated by separator
     * @param bool     $onlyPositiveRange optimization for shorter signs, uses 63 from 64 bits of sign part so serializer can work
     *                                    on positives number only. Better for performance in CustomSerializer.
     */
    public function __construct(
        #[\SensitiveParameter]
        string $secret,
        private SerializerContract $serializer,
        private string $hashAlgo = 'sha256',
        private string $separator = '.',
        ?int $maxSignLength = null,
        private int $signBits = 64,
        private bool $onlyPositiveRange = false,
    ) {
        if (\in_array($this->signBits, [64, 128, 192, 256], true) === false) {
            throw new \InvalidArgumentException('Allowed values for signBits are 64, 128, 192, 256');
        }

        $this->signParts = \intdiv($this->signBits, 64);

        $decodedSecret = \base64_decode($secret, true);

        if ($decodedSecret === false || \strlen($decodedSecret) < 16) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is at least 16 bytes long.'));
        }

        // sodium extension hashes
        if ($this->hashAlgo === 'siphash-2-4' || $this->hashAlgo === 'blake2b') {
            if (extension_loaded('sodium') === false) {
                throw new \RuntimeException('Sodium extension is not available for ' . $this->hashAlgo);
            }
            if ($this->hashAlgo === 'siphash-2-4') {
                // uses 16 bytes key
                $this->signFn = fn (string $data) => \sodium_crypto_shorthash($data, $decodedSecret);
            } else {
                // uses >= 16 bytes key
                $this->signFn = fn (string $data) => \sodium_crypto_generichash($data, $decodedSecret);
            }
        } // hash core extension hashes
        elseif (\in_array($this->hashAlgo, \hash_hmac_algos(), true)) {
            $this->signFn = fn (string $data) => \hash_hmac($this->hashAlgo, $data, $decodedSecret, true);
        } else {
            throw new \InvalidArgumentException("{$this->hashAlgo} is not supported");
        }

        // validate hash with given sign length
        if (@unpack('J' . $this->signParts, ($this->signFn)('test')) === false) {
            throw new \InvalidArgumentException("{$this->hashAlgo} cannot produce {$this->signBits} bits");
        }

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
    }

    /**
     * @param positive-int $bytes
     */
    public static function generateSecret(int $bytes = 16): string
    {
        return \base64_encode(\random_bytes($bytes));
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
            $hash = ($this->signFn)($data . $additionalData);
        } catch (\Exception $e) {
            throw new IdSigningException(message: $e->getMessage(), previous: $e);
        }

        $signData = @unpack('J' . $this->signParts, $hash); // unpack 64 bit chunks

        if ($signData === false) {
            throw new IdSigningException('Sign hash failed');
        }

        $result = '';

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
        }

        return $result;
    }

    public function getIdFromSigned(string $idWithSign, string $additionalData = ''): string
    {
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
