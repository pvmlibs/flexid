<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters\Ciphers;

use Pvmlibs\FlexId\Contracts\Cipher128Contract;

/**
 * Encrypts/decrypts id using Sparx64 algorithm. It operates on 128-bit block with 256-bit key, produces 128-bit output.
 * Depending on used serializer, max output length will be 22-32 chars (with default alphabets).
 * It doesn't require any php extensions.
 * Notes:
 * - for the same data (input id, secret) it will produce the same output
 * - when decrypting tampered output or with different key, it will produce some permutated data.
 *
 * See more https://www.cryptolux.org/index.php/SPARX
 */
class Sparx128Cipher implements Cipher128Contract
{
    public const N_STEPS = 10;
    public const ROUNDS_PER_STEP = 4;
    public const N_BRANCHES = 4;
    public const K_SIZE = 8;
    public const SECRET_SIZE = 32;

    /** @var array<non-negative-int, list<int>> Subkeys for encryption/decryption. */
    private array $subkeys = [];

    /** @var list<int> */
    private array $subkeyItem = [];

    /**
     * @param string $base64secret Secret key used by cipher. This needs to be treated with high confidentiality, do not
     *                             include it in source code. Can use generateSecret() method to produce the secret.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[\SensitiveParameter]
        string $base64secret,
    ) {
        $decodedSecret = \base64_decode($base64secret, true);

        if ($decodedSecret === false || \strlen($decodedSecret) !== self::SECRET_SIZE) {
            throw new \InvalidArgumentException(\sprintf('Secret key must be %d bytes long.', self::SECRET_SIZE));
        }

        $masterKey = [];
        for ($i = 0; $i < 2 * self::K_SIZE; $i++) {
            $masterKey[$i] = (\ord($decodedSecret[2 * $i + 1]) << 8) | \ord($decodedSecret[2 * $i]);
        }

        $this->subkeys = $this->keySchedule($masterKey);
        $this->subkeyItem = $this->subkeys[self::N_BRANCHES * self::N_STEPS];
    }

    public function encrypt(int $lowPart, int $highPart): array
    {
        static $mask = 0xFFFF;
        $x = [];
        $x[0] = $lowPart & $mask;
        $x[1] = ($lowPart >> 16) & $mask;
        $x[2] = ($lowPart >> 32) & $mask;
        $x[3] = ($lowPart >> 48) & $mask;
        $x[4] = $highPart & $mask;
        $x[5] = ($highPart >> 16) & $mask;
        $x[6] = ($highPart >> 32) & $mask;
        $x[7] = ($highPart >> 48) & $mask;

        $k = $this->subkeys;
        for ($s = 0; $s < self::N_STEPS; $s++) {

            for ($b = 0; $b < self::N_BRANCHES; $b++) {
                $subkeyItem = $k[self::N_BRANCHES * $s + $b];
                $xb = $x[2 * $b];
                $xb1 = $x[2 * $b + 1];

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
                $x[2 * $b] = $xb;
                $x[2 * $b + 1] = $xb1;
            }

            $tmp = ($x[0] ^ $x[1] ^ $x[2] ^ $x[3]) & $mask;
            $tmp = (($tmp << 8) | ($tmp >> 8)) & $mask;

            $x[4] ^= $x[2] ^ $tmp;
            $x[5] ^= $x[1] ^ $tmp;
            $x[6] ^= $x[0] ^ $tmp;
            $x[7] ^= $x[3] ^ $tmp;

            [$x[0], $x[4], $x[1], $x[5]] = [$x[4], $x[0], $x[5], $x[1]];
            [$x[2], $x[6], $x[3], $x[7]] = [$x[6], $x[2], $x[7], $x[3]];
        }

        for ($b = 0; $b < self::N_BRANCHES; $b++) {
            $x[2 * $b] ^= $this->subkeyItem[2 * $b];
            $x[2 * $b + 1] ^= $this->subkeyItem[2 * $b + 1];
        }

        return [
            $x[0] | ($x[1] << 16) | ($x[2] << 32) | ($x[3] << 48),
            $x[4] | ($x[5] << 16) | ($x[6] << 32) | ($x[7] << 48),
        ];
    }

    public function decrypt(int $lowPart, int $highPart): array
    {
        // decrypt part
        static $mask = 0xFFFF;

        $x = [];
        $x[0] = $lowPart & $mask;
        $x[1] = ($lowPart >> 16) & $mask;
        $x[2] = ($lowPart >> 32) & $mask;
        $x[3] = ($lowPart >> 48) & $mask;
        $x[4] = $highPart & $mask;
        $x[5] = ($highPart >> 16) & $mask;
        $x[6] = ($highPart >> 32) & $mask;
        $x[7] = ($highPart >> 48) & $mask;

        $k = $this->subkeys;

        for ($b = 0; $b < self::N_BRANCHES; $b++) {
            $x[2 * $b] ^= $this->subkeyItem[2 * $b];
            $x[2 * $b + 1] ^= $this->subkeyItem[2 * $b + 1];
        }

        for ($s = self::N_STEPS - 1; $s >= 0; $s--) {
            [$x[0], $x[4], $x[1], $x[5]] = [$x[4], $x[0], $x[5], $x[1]];
            [$x[2], $x[6], $x[3], $x[7]] = [$x[6], $x[2], $x[7], $x[3]];

            $tmp = ($x[0] ^ $x[1] ^ $x[2] ^ $x[3]) & $mask;
            $tmp = (($tmp << 8) | ($tmp >> 8)) & $mask;
            $x[4] ^= $x[2] ^ $tmp;
            $x[5] ^= $x[1] ^ $tmp;
            $x[6] ^= $x[0] ^ $tmp;
            $x[7] ^= $x[3] ^ $tmp;

            for ($b = 0; $b < self::N_BRANCHES; $b++) {
                $subkeyItem = $k[self::N_BRANCHES * $s + $b];
                $xb = $x[2 * $b];
                $xb1 = $x[2 * $b + 1];
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
                $x[2 * $b] = $xb;
                $x[2 * $b + 1] = $xb1;
            }
        }

        return [
            $x[0] | ($x[1] << 16) | ($x[2] << 32) | ($x[3] << 48),
            $x[4] | ($x[5] << 16) | ($x[6] << 32) | ($x[7] << 48),
        ];
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
     * Key permutation for 128/256 variant.
     *
     * @param array<non-negative-int, int> $key
     */
    private function kPerm128256(array &$key, int $roundConstant): void
    {
        [$key[0], $key[1]] = $this->arxRound($key[0], $key[1]);
        $key[2] = ($key[2] + $key[0]) & 0xFFFF;
        $key[3] = ($key[3] + $key[1]) & 0xFFFF;
        [$key[8], $key[9]] = $this->arxRound($key[8], $key[9]);
        $key[10] = ($key[10] + $key[8]) & 0xFFFF;
        $key[11] = ($key[11] + $key[9] + $roundConstant) & 0xFFFF;
        $tmp = [];

        for ($i = 0; $i < 6; $i++) {
            $tmp[$i] = $key[10 + $i];
        }
        for ($i = 15; $i >= 6; $i--) {
            $key[$i] = $key[$i - 6];
        }
        for ($i = 0; $i < 6; $i++) {
            $key[$i] = $tmp[$i];
        }
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
            $this->kPerm128256($masterKey, $c + 1);
        }

        return $subkeys;
    }
}
