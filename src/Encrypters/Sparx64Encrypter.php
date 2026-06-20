<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Contracts\EncrypterContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;

/**
 * Encrypts/decrypts id using Sparx64 algorithm. It operates on 64-bit block with 128-bit key, produces 64-bit output.
 * Depending on used serializer, max output length will be 11-16 chars (with default alphabets).
 * It doesn't require any php extensions.
 * Notes:
 * - for the same data (input id, additionalData, secret) it will produce the same output
 * - when decrypting tampered output, it will produce some random id
 * - it does not support additional data for authentication so encrypted id should be also signed.
 *
 * See more https://www.cryptolux.org/index.php/SPARX
 */
class Sparx64Encrypter implements EncrypterContract
{
    public const N_STEPS = 8;
    public const ROUNDS_PER_STEP = 3;
    public const N_BRANCHES = 2;
    public const K_SIZE = 4;
    public const SECRET_SIZE = 16;

    /** @var array<non-negative-int, list<int>> Subkeys for encryption/decryption. */
    private array $subkeys = [];
    /** @var array<int> */
    private array $subkeyItem = [];

    /**
     * @param string $secret Secret key used by cipher. This needs to be treated with high confidentiality, do not
     *                       include it in source code. Can use generateSecret() method to produce the secret.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[\SensitiveParameter]
        string $secret,
        private SerializerContract $serializer,
    ) {
        $decodedSecret = \base64_decode($secret, true);

        if ($decodedSecret === false || \strlen($decodedSecret) !== self::SECRET_SIZE) {
            throw new \InvalidArgumentException(\sprintf('Secret key must be %d bytes long.', self::SECRET_SIZE));
        }

        $masterKey = [];
        for ($i = 0; $i < 2 * self::K_SIZE; $i++) {
            $masterKey[$i] = (\ord($decodedSecret[2 * $i]) << 8) | \ord($decodedSecret[2 * $i + 1]);
        }
        $this->subkeys = $this->keySchedule($masterKey);
        $this->subkeyItem = $this->subkeys[self::N_BRANCHES * self::N_STEPS];
    }

    public static function generateSecret(): string
    {
        return \base64_encode(\random_bytes(self::SECRET_SIZE));
    }

    public function encrypt(int $id, string $additionalData = ''): string
    {
        static $mask = 0xFFFF;

        $x0 = $id & $mask;
        $x1 = ($id >> 16) & $mask;
        $x2 = ($id >> 32) & $mask;
        $x3 = ($id >> 48) & $mask;

        $k = $this->subkeys;
        for ($s = 0; $s < self::N_STEPS; $s++) {
            // for ($b = 0; $b < self::N_BRANCHES; $b++) {
            // branch 1
            $subkeyItem = $k[self::N_BRANCHES * $s];
            $xb = $x0;
            $xb1 = $x1;

            for ($r = 0; $r < self::ROUNDS_PER_STEP; $r++) {
                $rmul2 = $r << 1;
                $xb ^= $subkeyItem[$rmul2];
                $xb1 ^= $subkeyItem[$rmul2 + 1];

                $l2 = (($xb << 9) | ($xb >> 7)) & 0xFFFF;
                $l2 = ($l2 + $xb1) & 0xFFFF;
                $r2 = (($xb1 << 2) | ($xb1 >> 14)) & 0xFFFF;
                $r2 ^= $l2;

                $xb = $l2;
                $xb1 = $r2;
            }
            $x0 = $xb;
            $x1 = $xb1;
            // }

            // branch 2
            $subkeyItem = $k[self::N_BRANCHES * $s + 1];
            $xb = $x2;
            $xb1 = $x3;

            for ($r = 0; $r < self::ROUNDS_PER_STEP; $r++) {
                $rmul2 = $r << 1;
                $xb ^= $subkeyItem[$rmul2];
                $xb1 ^= $subkeyItem[$rmul2 + 1];

                $l2 = (($xb << 9) | ($xb >> 7)) & 0xFFFF;
                $l2 = ($l2 + $xb1) & 0xFFFF;
                $r2 = (($xb1 << 2) | ($xb1 >> 14)) & 0xFFFF;
                $r2 ^= $l2;

                $xb = $l2;
                $xb1 = $r2;
            }
            $x2 = $xb;
            $x3 = $xb1;
            // end branch 2

            $tmp = ((($x0 ^ $x1) << 8) | (($x0 ^ $x1) >> 8)) & 0xFFFF;
            $x2 ^= $x0 ^ $tmp;
            $x3 ^= $x1 ^ $tmp;

            $tmp = $x0;
            $x0 = $x2;
            $x2 = $tmp;
            $tmp = $x1;
            $x1 = $x3;
            $x3 = $tmp;
        }

        $x0 ^= $this->subkeyItem[0];
        $x1 ^= $this->subkeyItem[1];
        $x2 ^= $this->subkeyItem[2];
        $x3 ^= $this->subkeyItem[3];

        return $this->serializer->serialize($x0 | ($x1 << 16) | ($x2 << 32) | ($x3 << 48));
    }

    public function decrypt(string $id, string $additionalData = ''): int
    {
        $x64 = $this->serializer->deserialize($id);

        static $mask = 0xFFFF;

        $x0 = $x64 & $mask;
        $x1 = ($x64 >> 16) & $mask;
        $x2 = ($x64 >> 32) & $mask;
        $x3 = ($x64 >> 48) & $mask;

        $k = $this->subkeys;

        $x0 ^= $this->subkeyItem[0];
        $x1 ^= $this->subkeyItem[1];
        $x2 ^= $this->subkeyItem[2];
        $x3 ^= $this->subkeyItem[3];

        for ($s = self::N_STEPS - 1; $s >= 0; $s--) {
            $tmp = $x0;
            $x0 = $x2;
            $x2 = $tmp;
            $tmp = $x1;
            $x1 = $x3;
            $x3 = $tmp;

            $tmp = ((($x0 ^ $x1) << 8) | (($x0 ^ $x1) >> 8)) & 0xFFFF;
            $x2 ^= $x0 ^ $tmp;
            $x3 ^= $x1 ^ $tmp;

            // branch 1
            $subkeyItem = $k[self::N_BRANCHES * $s];
            $xb = $x0;
            $xb1 = $x1;
            for ($r = self::ROUNDS_PER_STEP - 1; $r >= 0; $r--) {
                $rmul2 = $r << 1;

                $r2 = $xb1 ^ $xb;
                $r2 = (($r2 << 14) | ($r2 >> 2)) & 0xFFFF;
                $l2 = ($xb - $r2) & 0xFFFF;
                $l2 = (($l2 << 7) | ($l2 >> 9)) & 0xFFFF;

                $xb = $l2;
                $xb1 = $r2;

                $xb ^= $subkeyItem[$rmul2];
                $xb1 ^= $subkeyItem[$rmul2 + 1];
            }
            $x0 = $xb;
            $x1 = $xb1;

            // branch 2
            $subkeyItem = $k[self::N_BRANCHES * $s + 1];
            $xb = $x2;
            $xb1 = $x3;
            for ($r = self::ROUNDS_PER_STEP - 1; $r >= 0; $r--) {
                $rmul2 = $r << 1;

                $r2 = $xb1 ^ $xb;
                $r2 = (($r2 << 14) | ($r2 >> 2)) & 0xFFFF;
                $l2 = ($xb - $r2) & 0xFFFF;
                $l2 = (($l2 << 7) | ($l2 >> 9)) & 0xFFFF;

                $xb = $l2;
                $xb1 = $r2;

                $xb ^= $subkeyItem[$rmul2];
                $xb1 ^= $subkeyItem[$rmul2 + 1];
            }
            $x2 = $xb;
            $x3 = $xb1;
        }

        $num = $x3 << 48;
        $num |= $x2 << 32;
        $num |= $x1 << 16;
        $num |= $x0;

        return $num;
    }

    /**
     * @return array{0: int, 1: int} each half of the branch
     */
    private function arxRound(int $leftHalfBranch, int $rightHalfBranch): array
    {
        $leftHalfBranch = (($leftHalfBranch << 9) | ($leftHalfBranch >> 7)) & 0xFFFF;
        $leftHalfBranch = ($leftHalfBranch + $rightHalfBranch) & 0xFFFF;
        $rightHalfBranch = (($rightHalfBranch << 2) | ($rightHalfBranch >> 14)) & 0xFFFF;
        $rightHalfBranch ^= $leftHalfBranch;

        return [$leftHalfBranch, $rightHalfBranch];
    }

    /**
     * Key permutation for 64/128 variant.
     *
     * @param array<non-negative-int, int> $key
     */
    private function kPerm64128(array &$key, int $roundConstant): void
    {
        [$key[0], $key[1]] = $this->arxRound($key[0], $key[1]);
        $key[2] = ($key[2] + $key[0]) & 0xFFFF;
        $key[3] = ($key[3] + $key[1]) & 0xFFFF;
        $key[7] = ($key[7] + $roundConstant) & 0xFFFF;
        [$tmp0, $tmp1] = [$key[6], $key[7]];
        for ($i = 7; $i >= 2; $i--) {
            $key[$i] = $key[$i - 2];
        }
        $key[0] = $tmp0;
        $key[1] = $tmp1;
    }

    /**
     * @param array<non-negative-int, int> $masterKey
     *
     * @return array<non-negative-int, list<int>>
     */
    private function keySchedule(array &$masterKey): array
    {
        $subkeys = [];
        $total = (self::N_BRANCHES * self::N_STEPS) + 1;
        for ($c = 0; $c < $total; $c++) {
            $subkeys[$c] = \array_slice($masterKey, 0, self::ROUNDS_PER_STEP << 1);
            $this->kPerm64128($masterKey, $c + 1);
        }

        return $subkeys;
    }

    public function maxOutputLength(): int
    {
        return $this->serializer->getMaxEncodedLength();
    }
}
