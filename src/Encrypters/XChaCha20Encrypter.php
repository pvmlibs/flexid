<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Contracts\EncrypterContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;

/**
 * Encrypt and authenticate with XChaCha20-Poly1305 algorithm using 256-bit key, 128-bit bytes MAC and 192 bits nonce.
 * Requires sodium extension. Does not support custom serializer, only build-in hex or base64 (url safe).
 * Notes:
 * - for the same data (input id, additionalData, secret) produces different output
 * - includes authentication (128-bit)
 * - outputs 64 (base64) - 96 (hex) chars.
 *
 * See more https://doc.libsodium.org/doc/secret-key_cryptography/aead
 */
class XChaCha20Encrypter implements EncrypterContract
{
    private string $key;

    /**
     * @var \Closure(string): string
     */
    private \Closure $encodeFn;

    /**
     * @var \Closure(string): (string|false)
     */
    private \Closure $decodeFn;

    /**
     * @var positive-int
     */
    private int $nonceLength = 24;

    private int $outputLength;

    /**
     * @param string $secret       Secret key used by cipher. This needs to be treated with high confidentiality, do not
     *                             include it in source code. Can use generateSecret() method to produce the secret.
     * @param bool   $base64Encode use url safe base64 encoding if true, otherwise hex encoding
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[\SensitiveParameter]
        string $secret,
        private bool $base64Encode = true,
    ) {
        if (extension_loaded('sodium') === false || function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') === false) {
            throw new \RuntimeException('Sodium extension or xchacha20poly1305_ietf function is not available');
        }
        $key = \base64_decode($secret, true);

        if ($key === false || \strlen($key) !== 32) {
            throw new \InvalidArgumentException(\sprintf('Secret key must be 32 bytes long.'));
        }
        $this->key = $key;

        if ($this->base64Encode) {
            $this->outputLength = 64;
            $this->encodeFn = fn (string $data) => \str_replace(['+', '/', '='], ['-', '_', ''], \base64_encode($data));
            $this->decodeFn = fn (string $data) => \base64_decode(\str_replace(['-', '_'], ['+', '/'], $data), true);
        } else {
            $this->outputLength = 96;
            $this->encodeFn = fn (string $data) => \bin2hex($data);
            $this->decodeFn = fn (string $data) => \hex2bin($data);
        }
    }

    public static function generateSecret(): string
    {
        return \base64_encode(\sodium_crypto_aead_chacha20poly1305_keygen());
    }

    public function encrypt(int $id, string $additionalData = ''): string
    {
        $nonce = \random_bytes($this->nonceLength);

        $ciphertext = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            message: \pack('J', $id),
            additional_data: $additionalData, // additional data for authentication
            nonce: $nonce,
            key: $this->key,
        );

        // nonce must be unique per encrypted id for safe encryption and stored with cipher text
        return ($this->encodeFn)($nonce . $ciphertext);
    }

    public function decrypt(string $id, string $additionalData = ''): int
    {
        if (\strlen($id) > $this->outputLength || $id === '') {
            throw new IdDecodeException('Encrypted ID is too long.');
        }

        $id = ($this->decodeFn)($id);

        if ($id === false) {
            throw new IdDecodeException('Invalid id to decrypt');
        }

        $nonce = substr($id, 0, $this->nonceLength);
        $idPart = substr($id, $this->nonceLength);

        try {
            $decrypted = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                ciphertext: $idPart,
                additional_data: $additionalData,
                nonce: $nonce,
                key: $this->key,
            );
        } catch (\SodiumException) {
            $decrypted = false;
        }

        if ($decrypted === false || \strlen($decrypted) !== 8) { // need exactly 8 bytes
            // for handling key rotation
            throw new IdDecryptException('Could not decrypt id');
        }

        $id = @unpack('J', $decrypted);

        if ($id === false) {
            throw new IdDecryptException('Could not extract id');
        }

        return $id[1];
    }

    public function maxOutputLength(): int
    {
        return $this->outputLength;
    }
}
