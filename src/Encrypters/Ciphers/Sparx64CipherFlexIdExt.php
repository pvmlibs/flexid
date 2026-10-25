<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters\Ciphers;

use Pvmlibs\FlexId\Contracts\Cipher64Contract;

/**
 * Encrypts/decrypts id using Sparx64 algorithm. It operates on 64-bit block with 128-bit key, produces 64-bit output.
 * Requires FlexId extension for efficient processing, interoperable with Sparx64Cipher.
 * Notes:
 * - for the same data (input id, additionalData, secret) it will produce the same output
 * - when decrypting tampered output or with different key, it will produce some permutated data.
 *
 * See more https://www.cryptolux.org/index.php/SPARX
 */
class Sparx64CipherFlexIdExt implements Cipher64Contract
{
    public const SECRET_SIZE = 16;

    private \FlexId\Sparx64 $cipher;

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
        $decodedSecret = \base64_decode($base64secret, true);

        if ($decodedSecret === false || \strlen($decodedSecret) < self::SECRET_SIZE) {
            throw new \InvalidArgumentException(\sprintf('Secret key must be base64 encoded and be at least %d bytes long.', self::SECRET_SIZE));
        }

        if (\strlen($decodedSecret) > self::SECRET_SIZE) {
            $decodedSecret = \substr($decodedSecret, 0, self::SECRET_SIZE);
        }

        $this->cipher = new \FlexId\Sparx64($decodedSecret);
    }

    public function encrypt(int $data): int
    {
        return $this->cipher->encrypt($data);
    }

    public function decrypt(int $data): int
    {
        return $this->cipher->decrypt($data);
    }
}
