<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\HMAC;

use Pvmlibs\FlexId\Contracts\HMACContract;

/**
 * Siphash-2-4 with fallback implementations flexid_utils extension -> sodium.
 * Fastest from HMACs.
 */
class SiphashHMAC implements HMACContract
{
    private HMACContract $hmac;

    public function __construct(
        #[\SensitiveParameter]
        string $base64EncodedKey,
        string $hkdfInfo = 'siphash mac',
        #[\SensitiveParameter]
        string $hkdfSalt = '',
    ) {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('flexid_utils') === false) {
            if (\extension_loaded('sodium') === false) {
                throw new \RuntimeException('Neither sodium or flexid extension is available');
            }
            $this->hmac = new SiphashHMACSodiumExt($base64EncodedKey, $hkdfInfo, $hkdfSalt);

        } else {
            $this->hmac = new SiphashHMACFlexIdExt($base64EncodedKey, $hkdfInfo, $hkdfSalt);
        }
        // @codeCoverageIgnoreEnd
    }

    public function setup(int $output64bitWords): void
    {
        $this->hmac->setup($output64bitWords);
    }

    public function sign(string $data, ?int $id = null): array
    {
        return $this->hmac->sign($data, $id);
    }
}
