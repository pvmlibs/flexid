<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\HMAC;

use Pvmlibs\FlexId\Contracts\HMACContract;
use Pvmlibs\FlexId\Exceptions\IdSigningException;

class SiphashHMACSodiumExt implements HMACContract
{
    private string $key;

    public function __construct(#[\SensitiveParameter] string $base64EncodedKey, string $hkdfInfo = 'siphash mac')
    {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('sodium') === false) {
            throw new \RuntimeException('Flexid extension is not available');
        }
        // @codeCoverageIgnoreEnd

        $decodedKey = \base64_decode($base64EncodedKey, true);

        if ($decodedKey === false || \strlen($decodedKey) < 16) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is at least 16 bytes long.'));
        }
        $this->key = \hash_hkdf('sha3-256', $decodedKey, 16, $hkdfInfo);
    }

    public function setup(int $output64bitWords): void
    {
        if ($output64bitWords !== 1) {
            throw new \InvalidArgumentException(\sprintf('Siphash support only 64 bit output'));
        }
    }

    public function sign(string $data, ?int $id = null): array
    {
        $hash = \sodium_crypto_shorthash(($id !== null ? \pack('Q', $id) : '') . $data, $this->key);
        $result = \unpack('Q', $hash);

        if ($result === false) {
            throw new IdSigningException('Sign failed');
        }

        return [$result[1]];
    }
}
