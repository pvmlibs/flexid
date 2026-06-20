<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Contracts\EncrypterContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Encrypts/decrypts id using AES. Supports 128, 196 and 256-bit keys. Includes 64-bit MAC. Outputs 128-bit of data.
 * It uses ecb mode, but it does not matter from safety point as we do not chain blocks and always use one, consisting of
 * 64-bit id and 64-bit MAC. Requires openssl extension, sodium is optional for faster MAC algorithms.
 * Depending on used serializer, max output length will be 11-16 chars (with default alphabets).
 * Notes:
 * - for the same data (input id, additionalData, secret) produces the same output
 * - includes authentication (64-bit) to utilize 128-bit block space - with MAC-then-encrypt
 *   approach so authentication is done after decrypting.
 */
class AesEncrypter implements EncrypterContract
{
    protected string $secret;
    protected string $cipher;

    protected int $maxOutputChars;

    /**
     * @var \Closure(string): string
     */
    private \Closure $signFn;

    /**
     * @param string $secret Secret key used by cipher and hashed version for MAC. This needs to be treated with high
     *                       confidentiality, do not include it in source code. Can use generateSecret() method to produce
     *                       the secret.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[\SensitiveParameter]
        string $secret,
        private string $macHash = 'sha256',
        public ?SerializerContract $serializer = null,
        public string $separator = '.',
    ) {
        $decodedCipherKey = \base64_decode($secret, true);

        if ($decodedCipherKey === false) {
            throw new \InvalidArgumentException('Secret key for AES must have at least 16 bytes');
        }

        match (\strlen($decodedCipherKey)) {
            16 => $this->cipher = 'aes-128-ecb',
            24 => $this->cipher = 'aes-192-ecb',
            32 => $this->cipher = 'aes-256-ecb',
            default => throw new \InvalidArgumentException('Unsupported key size, must be 16, 24 or 32 bytes'),
        };

        $this->secret = $decodedCipherKey;

        $authenticateKey = \hash('sha256', $decodedCipherKey, true);

        // sodium extension hashes
        if ($this->macHash === 'siphash-2-4' || $this->macHash === 'blake2b') {
            if (extension_loaded('sodium') === false) {
                throw new \RuntimeException('Sodium extension is not available for ' . $this->macHash);
            }

            if ($this->macHash === 'siphash-2-4') {
                // uses 16 bytes key
                $authenticateKey = \substr($authenticateKey, 0, 16);
                $this->signFn = fn (string $data) => \sodium_crypto_shorthash($data, $authenticateKey);
            } else {
                // uses >= 16 bytes key
                $this->signFn = fn (string $data) => \substr(\sodium_crypto_generichash($data, $authenticateKey), 0, 8);
            }
        } // hash core extension hashes
        elseif (\in_array($this->macHash, \hash_hmac_algos(), true)) {
            $this->signFn = fn (string $data) => \substr(\hash_hmac($this->macHash, $data, $authenticateKey, true), 0, 8);
        } else {
            throw new \InvalidArgumentException("{$this->macHash} is not supported");
        }

        if (\strlen($this->separator) !== 1) {
            throw new \InvalidArgumentException('Sign separator must be a single character');
        }
        if ($this->serializer !== null) {
            if (str_contains($this->serializer->getAlphabet(), $this->separator)) {
                throw new \InvalidArgumentException(sprintf('Signer serializer cannot include separator char %s in alphabet', $this->separator));
            }
            $this->maxOutputChars = $this->serializer->getMaxEncodedLength() * 2;
        } else {
            $this->maxOutputChars = 32; // hex, 16 bytes
        }
    }

    /**
     * @param positive-int $bytes
     */
    public static function generateSecret(int $bytes = 32): string
    {
        return \base64_encode(\random_bytes($bytes));
    }

    public function encrypt(int $id, string $additionalData = ''): string
    {
        $plaintext = pack('J', $id);
        $sign = ($this->signFn)($plaintext . $additionalData);
        $ciphertext = openssl_encrypt($plaintext . $sign, $this->cipher, $this->secret, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        if ($ciphertext === false) {
            throw new IdEncodeException('Failed to encrypt data');
        }

        if ($this->serializer === null) {
            return \bin2hex($ciphertext);
        }

        // unpack 2 * 8 bytes, 128bit
        $toSerialize = @\unpack('J2', $ciphertext);
        if ($toSerialize === false) {
            throw new IdEncodeException('Failed to unpack data');
        }

        $encoded = $this->serializer->serialize($toSerialize[1]);
        $part2 = $this->serializer->serialize($toSerialize[2]);

        if (\strlen($part2) < $this->serializer->getMaxEncodedLength()) {
            $encoded .= $this->separator;
        }

        return $encoded . $part2;
    }

    public function decrypt(string $id, string $additionalData = ''): int
    {
        $idEncodedLength = \strlen($id);
        if ($idEncodedLength > $this->maxOutputChars || $idEncodedLength === 0) {
            throw new IdDecodeException('Wrong input length');
        }

        if ($this->serializer === null) {
            $ciphertext = @\hex2bin($id);

            if ($ciphertext === false) {
                throw new IdDecodeException('Failed to deserialize data');
            }
        } else {
            $maxPartLength = $this->serializer->getMaxEncodedLength();

            $separatorPos = \strrpos($id, $this->separator);
            if ($separatorPos === false) {
                $idEncodedPart2 = \substr($id, -$maxPartLength);
                $idEncodedPart1 = \substr($id, 0, $idEncodedLength - \strlen($idEncodedPart2));
            } else {
                $idEncodedPart1 = \substr($id, 0, $separatorPos);
                $idEncodedPart2 = \substr($id, $separatorPos + 1);
            }

            $idEncodedPart1 = @pack('J', $this->serializer->deserialize($idEncodedPart1));
            $idEncodedPart2 = @pack('J', $this->serializer->deserialize($idEncodedPart2));

            $ciphertext = $idEncodedPart1 . $idEncodedPart2;
        }

        $plaintext = openssl_decrypt($ciphertext, $this->cipher, $this->secret, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        if ($plaintext === false) {
            throw new IdDecryptException();
        }

        $idPart = \substr($plaintext, 0, 8);
        $signPart = \substr($plaintext, 8);

        $sign = ($this->signFn)($idPart . $additionalData);

        if (\hash_equals($sign, $signPart)) {
            $id = @\unpack('J', $idPart);
            if ($id === false) {
                throw new IdDecryptException();
            }

            return $id[1];
        }
        throw new IdBadSignException();
    }

    public function maxOutputLength(): int
    {
        return $this->maxOutputChars;
    }
}
