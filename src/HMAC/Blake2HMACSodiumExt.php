<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\HMAC;

use Pvmlibs\FlexId\Contracts\HMACContract;
use Pvmlibs\FlexId\Exceptions\IdSigningException;

class Blake2HMACSodiumExt implements HMACContract
{
    private string $key;
    private string $unpackFormat = 'Q';

    public function __construct(#[\SensitiveParameter] string $base64EncodedKey, string $hkdfInfo = 'blake2 mac')
    {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('sodium') === false) {
            throw new \RuntimeException('Flexid extension is not available');
        }
        // @codeCoverageIgnoreEnd

        $decodedKey = \base64_decode($base64EncodedKey, true);

        if ($decodedKey === false || \strlen($decodedKey) < 32) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is at least 32 bytes long.'));
        }
        $this->key = \hash_hkdf('sha3-256', $decodedKey, 32, $hkdfInfo);
    }

    public function setup(int $output64bitWords): void
    {
        if ($output64bitWords < 1 || $output64bitWords > 4) {
            throw new \InvalidArgumentException(\sprintf('Blake2 support 64 - 256 bits output'));
        }
        $this->unpackFormat = 'Q' . $output64bitWords;
    }

    public function sign(string $data, ?int $id = null): array
    {
        $hash = \sodium_crypto_generichash(($id !== null ? \pack($this->unpackFormat, $id) : '') . $data, $this->key);
        $result = \unpack($this->unpackFormat, $hash);

        if ($result === false) {
            throw new IdSigningException('Sign hash failed');
        }

        return \array_values($result);
    }
}
