<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Signers;

use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;

/**
 * Faster version (3-5x) of Signer using only SipHash-2-4 and fixed hexadecimal encoding.
 */
class FastSigner implements SignerContract
{
    private readonly string $secret;

    /**
     * @param string $key    Secret key used in MAC. This needs to be treated with high confidentiality, do not
     *                       include it in source code. Can use generateKey() method to produce the key. In case of
     *                       non-cryptographic algorithms, key is concatenated to id (less secure).
     * @param int    $length Limits sign max length. This lowers security, 16 is max. In some cases
     *                       when still want the shortest possible id, short sign may just work like crc for id.
     *                       Limiting does not affect performance.
     * @param string $salt   Use additional salt for more entropy
     */
    public function __construct(
        #[\SensitiveParameter]
        string $key,
        private int $length = 16,
        private string $salt = '',
    ) {
        $secret = \base64_decode($key, true);

        if ($secret === false || strlen($secret) !== 16) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is 16 bytes long.'));
        }

        $this->secret = $secret;

        if ($this->length < 1 || $this->length > 16) {
            throw new \InvalidArgumentException(\sprintf('Secret key is invalid. Ensure it is base64 encoded string and is 16 bytes long.'));
        }
    }

    public static function generateKey(): string
    {
        return \base64_encode(\random_bytes(16));
    }

    public function getSignedId(string $id): string
    {
        return $id . $this->sign($id);
    }

    private function sign(string $data): string
    {
        if ($data === '') {
            throw new IdSigningException('Empty id to sign');
        }

        return \substr(\bin2hex(\sodium_crypto_shorthash($data . $this->salt, $this->secret)), 0, $this->length);
    }

    public function getIdFromSigned(string $idWithSign): string
    {
        if ($idWithSign === '') {
            throw new IdVerifySignException('Id is empty');
        }

        if (\strlen($idWithSign) > 32) {
            // max 16 id, 16 sign
            throw new IdVerifySignException('Id is too long ' . $idWithSign);
        }

        $id = \substr($idWithSign, 0, \strlen($idWithSign) - $this->length);
        $signPart = \substr($idWithSign, -$this->length);

        if ($id === '' || hash_equals($this->sign($id), $signPart) === false) {
            throw new IdVerifySignException("Invalid signature {$signPart} for id {$idWithSign}, {$id}");
        }

        return $id;
    }

    public function getAlphabet(): string
    {
        return '0123456789abcdef';
    }
}
