<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\HMAC;

use Pvmlibs\FlexId\Contracts\HMACContract;
use Pvmlibs\FlexId\Exceptions\IdSigningException;

class Sha256HMAC implements HMACContract
{
    private string $unpackFormat = 'Q';
    private string $key;

    public function __construct(
        #[\SensitiveParameter]
        string $base64EncodedKey,
        string $hkdfInfo = 'sha256 mac',
        #[\SensitiveParameter]
        string $hkdfSalt = '',
    ) {
        $decodedKey = \base64_decode($base64EncodedKey, true);

        if ($decodedKey === false || \strlen($decodedKey) < 32) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is at least 32 bytes long.'));
        }
        $decodedSalt = \base64_decode($hkdfSalt, true);

        if ($decodedSalt === false) {
            throw new \InvalidArgumentException(\sprintf('Salt must be base64 encoded'));
        }
        $this->key = \hash_hkdf('sha3-256', $decodedKey, 32, $hkdfInfo, $decodedSalt);
    }

    public function setup(int $output64bitWords): void
    {
        if ($output64bitWords < 1 || $output64bitWords > 4) {
            throw new \InvalidArgumentException(\sprintf('SHA-256 supports 64-256 bits output'));
        }

        $this->unpackFormat = 'Q' . $output64bitWords;
    }

    public function sign(string $data, ?int $id = null): array
    {
        $signData = @unpack(
            $this->unpackFormat,
            \hash_hmac('sha256', ($id !== null ? pack('Q', $id) : '') . $data, $this->key, true),
        );
        if ($signData === false) {
            throw new IdSigningException('Sign hash failed');
        }

        return array_values($signData);
    }
}
