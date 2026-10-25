<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters\Ciphers;

use Pvmlibs\FlexId\Contracts\Cipher128Contract;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * Encrypts/decrypts id using AES algorithm. It operates on 128-bit block with 256-bit key, produces 128-bit output.
 * Requires openssl extension.
 * Notes:
 * - for the same data (input id, additionalData, secret) it will produce the same output
 * - when decrypting tampered output or with different key, it will produce some permutated data.
 */
class AesCipher implements Cipher128Contract
{
    private string $key;
    /**
     * @param string $base64secret Secret key used by cipher. This needs to be treated with high confidentiality, do not
     *                             include it in source code. Can use generateSecret() method to produce the secret.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[\SensitiveParameter]
        string $base64secret,
    ) {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('openssl') === false) {
            throw new \RuntimeException('Openssl extension is not available');
        }
        // @codeCoverageIgnoreEnd

        $decodedSecret = \base64_decode($base64secret, true);

        if ($decodedSecret === false || \strlen($decodedSecret) !== 32) {
            throw new \InvalidArgumentException(\sprintf('Secret key must be 20 bytes long.'));
        }

        $this->key = $decodedSecret;
    }

    public function encrypt(int $lowPart, int $highPart): array
    {
        $serialized = pack('Q2', $lowPart, $highPart);
        $ciphertext = \openssl_encrypt($serialized, 'aes-256-ecb', $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        if ($ciphertext === false) {
            throw new IdEncodeException('Failed to encrypt data');
        }

        // unpack 2 * 8 bytes, 128bit
        $unpacked = @\unpack('Q2', $ciphertext);

        if ($unpacked === false) {
            throw new IdEncodeException('Failed to encrypt data');
        }

        return \array_values($unpacked);
    }

    public function decrypt(int $lowPart, int $highPart): array
    {
        $serialized = pack('Q2', $lowPart, $highPart);
        $plaintext = \openssl_decrypt($serialized, 'aes-256-ecb', $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        if ($plaintext === false) {
            throw new IdDecryptException('Failed to encrypt data');
        }

        // unpack 2 * 8 bytes, 128bit
        $unpacked = @\unpack('Q2', $plaintext);

        if ($unpacked === false) {
            throw new IdDecryptException('Failed to encrypt data');
        }

        return \array_values($unpacked);
    }
}
