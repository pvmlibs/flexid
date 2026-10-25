<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters\Ciphers;

use Pvmlibs\FlexId\Contracts\Cipher128Contract;

/**
 * Encrypts/decrypts id using Sparx64 algorithm. It operates on 128-bit block with 256-bit key, produces 128-bit output.
 * Requires FlexId extension for efficient processing, interoperable with Sparx128Cipher.
 * Notes:
 * - for the same data (input id, secret) it will produce the same output
 * - when decrypting tampered output or with different key, it will produce some permutated data.
 *
 * See more https://www.cryptolux.org/index.php/SPARX
 */
class Sparx128CipherFlexIdExt implements Cipher128Contract
{
    public const SECRET_SIZE = 32;

    private \FlexId\Sparx128 $cipher;

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
        if (\extension_loaded('flexid_utils') === false) {
            throw new \RuntimeException('Flexid extension is not available');
        }
        // @codeCoverageIgnoreEnd

        $decodedSecret = \base64_decode($base64secret, true);

        if ($decodedSecret === false || \strlen($decodedSecret) !== self::SECRET_SIZE) {
            throw new \InvalidArgumentException(\sprintf('Secret key must be %d bytes long.', self::SECRET_SIZE));
        }

        $this->cipher = new \FlexId\Sparx128($decodedSecret);
    }

    public function encrypt(int $lowPart, int $highPart): array
    {
        return $this->cipher->encrypt($lowPart, $highPart);
    }

    public function decrypt(int $lowPart, int $highPart): array
    {
        return $this->cipher->decrypt($lowPart, $highPart);
    }
}
