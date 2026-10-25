<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\HMAC;

use Pvmlibs\FlexId\Contracts\HMACContract;

/**
 * Fastest from strong cryptographic keyed hash.
 */
class Blake3HMACFlexIdExt implements HMACContract
{
    private \FlexId\Blake3 $hash;

    private int $parts = 1;

    public function __construct(#[\SensitiveParameter] string $base64EncodedKey, string $hkdfInfo = 'blake3 mac')
    {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('flexid_utils') === false) {
            throw new \RuntimeException('Flexid extension is not available');
        }
        // @codeCoverageIgnoreEnd

        $decodedKey = \base64_decode($base64EncodedKey, true);

        if ($decodedKey === false || \strlen($decodedKey) < 32) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is at least 32 bytes long.'));
        }
        $derivedKey = \hash_hkdf('sha3-256', $decodedKey, 32, $hkdfInfo);
        $this->hash = new \FlexId\Blake3($derivedKey);
    }

    public function setup(int $output64bitWords): void
    {
        if ($output64bitWords < 1 || $output64bitWords > 4) {
            throw new \InvalidArgumentException(\sprintf('Blake3 support 64 - 256 bits output'));
        }
        $this->parts = $output64bitWords;
    }

    public function sign(string $data, ?int $id = null): array
    {
        try {
            return $this->hash->hash($data, $id, $this->parts);
        } catch (\ValueError $ex) {
            throw new \InvalidArgumentException($ex->getMessage());
        }
    }
}
