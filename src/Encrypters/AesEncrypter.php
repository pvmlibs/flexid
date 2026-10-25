<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Contracts\HMACContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Encrypters\Abstract\Generic128EncrypterWithMac;
use Pvmlibs\FlexId\Encrypters\Ciphers\AesCipher;

/**
 * Encrypts/decrypts id and serializes using AES with 64-bit MAC.
 * Depending on serializer, output should be ~22-32 characters.
 * It uses ecb mode, but it does not matter from safety point as we do not chain blocks and always use one,
 * consisting of 64-bit id and 64-bit MAC.
 * Notes:
 * - for the same data (input id, additionalData, secret) produces the same output
 * - includes authentication (64-bit) to utilize 128-bit block space - with MAC-then-encrypt approach
 * - first use has ~1ms additional latency for AES preparation.
 */
class AesEncrypter extends Generic128EncrypterWithMac
{
    public function __construct(
        #[\SensitiveParameter]
        string $base64secret,
        HMACContract $hmac,
        SerializerContract $serializer,
        string $separator = '.',
    ) {
        parent::__construct(new AesCipher($base64secret), $hmac, $serializer, $separator);
    }
}
