<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Contracts\EncrypterWithMacContract;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Tools\Alphabets;

/**
 * Turbo version of Sparx128Encrypter, using only Flexid extension utilities.
 */
class Sparx128TurboEncrypter implements EncrypterWithMacContract
{
    private \FlexId\Sparx128 $cipher;
    private \FlexId\Encoder $encoder;
    private \FlexId\Siphash $hmac;

    private int $maxOutputChars;

    /**
     * @param string $base64secret 32-bit secret in base64 encoding. It will be also used for MAC, using HKDF.
     * @param string $signHkdfInfo info message for HKDF
     * @param string $alphabet     alphabet for serializing data
     * @param string $separator    character used to separate two 64-bit parts of serialized data. It is used only when necessary.
     * @param string $hkdfSalt     base64 encoded salt for HKDF
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[\SensitiveParameter]
        string $base64secret,
        string $signHkdfInfo = 'siphash mac',
        string $alphabet = Alphabets::CUSTOM_SEMI_SAFE,
        string $separator = '.',
        #[\SensitiveParameter]
        string $hkdfSalt = '',
    ) {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('flexid_utils') === false) {
            throw new \RuntimeException('Flexid extension is not available');
        }
        // @codeCoverageIgnoreEnd

        $decodedSecret = \base64_decode($base64secret, true);

        if ($decodedSecret === false || \strlen($decodedSecret) !== 32) {
            throw new \InvalidArgumentException(\sprintf('Secret key must be 32 bytes long'));
        }
        $decodedSalt = \base64_decode($hkdfSalt, true);

        if ($decodedSalt === false) {
            throw new \InvalidArgumentException(\sprintf('Salt must be base64 encoded'));
        }
        $derivedKey = \hash_hkdf('sha3-256', $decodedSecret, 16, $signHkdfInfo, $decodedSalt);

        try {
            $this->cipher = new \FlexId\Sparx128($decodedSecret);
            $this->encoder = new \FlexId\Encoder($alphabet, $separator);
            $this->hmac = new \FlexId\Siphash($derivedKey);
        } catch (\ValueError $ex) {
            throw new \InvalidArgumentException($ex->getMessage());
        }
        $this->maxOutputChars = $this->encoder->getMaxEncodedLength() * 2;
    }

    public function encrypt(int $id, string $additionalData = ''): string
    {
        $sign = $this->hmac->hash($additionalData, $id);

        $encrypted = $this->cipher->encrypt($id, $sign);

        return $this->encoder->doubleEncode(...$encrypted);
    }

    public function decrypt(string $id, string $additionalData = ''): int
    {
        try {
            $decrypted = $this->cipher->decrypt(...$this->encoder->doubleDecode($id));
        } catch (\ValueError $ex) {
            throw new IdDecodeException($ex->getMessage());
        }
        $sign = $this->hmac->hash($additionalData, $decrypted[0]);

        if ($sign === $decrypted[1]) {
            return $decrypted[0];
        }

        throw new IdBadSignException();
    }

    public function maxOutputLength(): int
    {
        return $this->maxOutputChars;
    }
}
