<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Signers;

use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;
use Pvmlibs\FlexId\Serializers\SerializerContract;

/**
 * Signer for ensuring id integrity and authenticity by implementing MAC (Message Authentication Code).
 * Uses 64 bits of hash.
 */
class Signer implements SignerContract
{
    /**
     * @var \Closure(string): string
     */
    private \Closure $signFn;

    /**
     * @param string $key           Secret key used in MAC. This needs to be treated with high confidentiality, do not
     *                              include it in source code. Can use generateKey() method to produce the key. In case of
     *                              non-cryptographic algorithms, key is concatenated to id (less secure).
     * @param string $hashAlgo      hash algorithm, recommended ones in order (for security/speed ratio): siphash-2-4, blake2b, sha256.
     *                              Note that only first 64 bits are taken.
     * @param string $separator     In variable length sign used to separate id part from sign with separator.
     *                              If empty and serializer is constant length or maxSignLength = 1 it will take accordingly last
     *                              16 or 1 chars to verify sign. When serializer is not constant length then it assumes
     *                              that id is <b>(always 16 chars)</b> and starts from 17th char to the end.
     * @param int    $maxSignLength Limit sign up to that many characters. This lowers security, 16 is max. In some cases
     *                              when still want the shortest possible id, it may just work like crc when maxSignLength = 1
     *                              and empty separator
     * @param string $salt          Use additional salt for more entropy
     */
    public function __construct(
        private SerializerContract $serializer,
        #[\SensitiveParameter]
        string $key,
        private string $hashAlgo = 'siphash-2-4',
        private string $separator = '.',
        private int $maxSignLength = 16,
        string $salt = '',
    ) {
        $decodedSecret = \base64_decode($key, true);

        if ($decodedSecret === false || \strlen($decodedSecret) < 16) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is at least 16 bytes long.'));
        }

        if ($this->hashAlgo === 'siphash-2-4') { // uses 16 bytes key
            $this->signFn = fn (string $data) => \sodium_crypto_shorthash($data . $salt, $decodedSecret);
        } elseif ($this->hashAlgo === 'blake2b') {
            $this->signFn = fn (string $data) => \sodium_crypto_generichash($data . $salt, $decodedSecret);
        } elseif (\in_array($this->hashAlgo, \hash_hmac_algos(), true)) {
            $this->signFn = fn (string $data) => \hash_hmac($this->hashAlgo, $data . $salt, $decodedSecret, true);
        } else {
            $this->signFn = fn (string $data) => hash($this->hashAlgo, $data . $salt . $decodedSecret, true);
        }

        if ($maxSignLength < 1 || $maxSignLength > 16) {
            throw new \InvalidArgumentException('Sign max length must be between 1 and 16');
        }

        if (\strlen($this->separator) > 1) {
            throw new \InvalidArgumentException('Sign separator must be a single character or empty');
        }
        if (\strlen($this->separator) === 1 && str_contains($this->serializer->getAlphabet(), $this->separator)) {
            throw new \InvalidArgumentException(sprintf('Signer serializer cannot include separator char in alphabet'));
        }
    }

    /**
     * @param int<1, max> $bytes
     */
    public static function generateKey(int $bytes = 16): string
    {
        return \base64_encode(\random_bytes($bytes));
    }

    public function getSignedId(string $id): string
    {
        return $id . $this->separator . $this->sign($id);
    }

    private function sign(string $data): string
    {
        if ($data === '') {
            throw new IdSigningException('Empty id to sign');
        }

        if ($this->separator === '' && $this->serializer->isConstantLength() === false
        && $this->maxSignLength > 1 && \strlen($data) !== 16) {
            throw new IdSigningException('Id must be 16 characters if no separator is used and signer serializer is variable length');
        }

        $hash = ($this->signFn)($data);

        $sign = unpack('n4', $hash); // unpack 64 bits

        if ($sign === false) {
            throw new IdSigningException('Sign hash failed');
        }

        return \substr($this->serializer->serialize(\array_values($sign)), 0, $this->maxSignLength);
    }

    public function getIdFromSigned(string $idWithSign): string
    {
        if ($idWithSign === '') {
            throw new IdVerifySignException('Id is empty');
        }

        if (\strlen($idWithSign) > 33) {
            // max 16 id, 16 sign + separator
            throw new IdVerifySignException('Id is too long ' . $idWithSign);
        }

        if ($this->separator !== '') {
            // find separator pos from the end
            $separatorPos = \strrpos($idWithSign, $this->separator);
            if ($separatorPos === false) {
                throw new IdVerifySignException('Id has no signature separator');
            }
            $signPartPos = $separatorPos + 1;
        } else {
            if ($this->serializer->isConstantLength() || $this->maxSignLength === 1) {
                $separatorPos = $signPartPos = \strlen($idWithSign) - $this->maxSignLength;
            } else {
                // assume id has constant length
                $separatorPos = $signPartPos = 16;
            }
        }

        $id = \substr($idWithSign, 0, $separatorPos);
        $signPart = \substr($idWithSign, $signPartPos);

        if ($id === '' || hash_equals($this->sign($id), $signPart) === false) {
            throw new IdVerifySignException("Invalid signature {$signPart} for id {$idWithSign}");
        }

        return $id;
    }

    public function getAlphabet(): string
    {
        return $this->serializer->getAlphabet();
    }
}
