<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Tools;

class KeyGen
{
    // Key length 32 bytes is sufficient for all ciphers and hmac
    public static function generate(): string
    {
        return \base64_encode(\random_bytes(32));
    }
}
