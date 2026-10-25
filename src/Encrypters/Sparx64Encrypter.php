<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Contracts\Cipher64Contract;
use Pvmlibs\FlexId\Contracts\EncrypterContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx64Cipher;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx64CipherFlexIdExt;

/**
 * Encrypts/decrypts id and serializes using Sparx.
 * Depending on serializer, output should be ~11-13 characters.
 * It doesn't require any php extensions, but will try using FlexId extension if available for much better performance.
 * Notes:
 * - for the same data (input id, secret) it will produce the same output
 * - when decrypting tampered output, it will produce some random id
 * - it does not support additional data for authentication. You can use Signer for additional authentication.
 *
 * See more https://www.cryptolux.org/index.php/SPARX
 */
class Sparx64Encrypter implements EncrypterContract
{
    private Cipher64Contract $cipher;

    public function __construct(
        #[\SensitiveParameter]
        string $base64secret,
        private SerializerContract $serializer,
        bool $forcePHPImplementation = false,
    ) {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('flexid_utils') === false || $forcePHPImplementation) {
            $this->cipher = new Sparx64Cipher($base64secret);
        } else {
            $this->cipher = new Sparx64CipherFlexIdExt($base64secret);
        }
        // @codeCoverageIgnoreEnd
    }

    public function encrypt(int $id): string
    {
        return $this->serializer->serialize($this->cipher->encrypt($id));
    }

    public function decrypt(string $id): int
    {
        return $this->cipher->decrypt($this->serializer->deserialize($id));
    }

    public function maxOutputLength(): int
    {
        return $this->serializer->getMaxEncodedLength();
    }
}
