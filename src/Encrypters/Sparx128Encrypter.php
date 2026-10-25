<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Contracts\HMACContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Encrypters\Abstract\Generic128EncrypterWithMac;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx128Cipher;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx128CipherFlexIdExt;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

/**
 * Encrypts/decrypts id and serializes using Sparx128. It operates on 128-bit block with 256-bit key, produces 128-bit output.
 * 64 bits of plaintext will be used on MAC.
 * Depending on used serializer, max output length will be ~22-32 chars.
 * It doesn't require any php extensions, but will try using FlexId extension if available for much better performance.
 * Notes:
 * - for the same data (input id, additionalData, secret) it will produce the same output
 * - when decrypting tampered output, it will throw IdDecodeException|IdBadSignException exception (MAC mismatch)
 * - includes authentication (64-bit) to utilize 128-bit block space - with MAC-then-encrypt approach.
 *
 * See more https://www.cryptolux.org/index.php/SPARX
 */
class Sparx128Encrypter extends Generic128EncrypterWithMac
{
    public function __construct(
        #[\SensitiveParameter]
        string $base64secret,
        HMACContract $hmac,
        SerializerContract $serializer,
        bool $forcePHPImplementation = false,
        string $separator = '.',
    ) {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('flexid_utils') === false || $forcePHPImplementation) {
            $cipher = new Sparx128Cipher($base64secret);
        } else {
            $cipher = new Sparx128CipherFlexIdExt($base64secret);
        }
        // @codeCoverageIgnoreEnd
        parent::__construct($cipher, $hmac, $serializer, $separator);
    }
}
