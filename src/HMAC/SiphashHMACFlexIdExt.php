<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\HMAC;

use Pvmlibs\FlexId\Contracts\HMACContract;

class SiphashHMACFlexIdExt implements HMACContract
{
    protected \FlexId\Siphash $hash;

    public function __construct(
        #[\SensitiveParameter]
        string $base64EncodedKey,
        string $hkdfInfo = 'siphash mac',
        #[\SensitiveParameter]
        string $hkdfSalt = '',
    ) {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('flexid_utils') === false) {
            throw new \RuntimeException('Flexid extension is not available');
        }
        // @codeCoverageIgnoreEnd

        $decodedKey = \base64_decode($base64EncodedKey, true);

        if ($decodedKey === false || \strlen($decodedKey) < 16) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is at least 16 bytes long.'));
        }
        $decodedSalt = \base64_decode($hkdfSalt, true);

        if ($decodedSalt === false) {
            throw new \InvalidArgumentException(\sprintf('Salt must be base64 encoded'));
        }
        $derivedKey = \hash_hkdf('sha3-256', $decodedKey, 16, $hkdfInfo, $decodedSalt);
        $this->hash = new \FlexId\Siphash($derivedKey);
    }

    public function setup(int $output64bitWords): void
    {
        if ($output64bitWords !== 1) {
            throw new \InvalidArgumentException(\sprintf('Siphash support only 64 bit output'));
        }
    }

    public function sign(string $data, ?int $id = null): array
    {
        return [$this->hash->hash($data, $id)];
    }
}
