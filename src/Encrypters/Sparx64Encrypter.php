<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Serializers\SerializerContract;

/**
 * Encrypts/decrypts id using Sparx64 algorithm. This can be used when raw id are considered
 * especially sensitive.
 * It doesn't require any extensions if used with NativeSerializer.
 * About 12-15x slower than using only encoders for raw id, but still can be fast enough.
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

    /**
     * @param string $secret Secret used by cipher. This needs to be treated with high confidentiality, do not
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
    }

    public static function generateSecret(): string
    {
        return \base64_encode(\random_bytes(self::SECRET_SIZE));
    }

    public function encrypt(int $id): string
    {
        if ($id < 0) {
            throw new IdEncodeException('Encrypted ID must be positive.');
        }

        $mask = 0xFFFF;
        $x = [
            $id & $mask,
            ($id >> 16) & $mask,
            ($id >> 32) & $mask,
            ($id >> 48) & $mask,
        ];

        $k = $this->subkeys;
        for ($s = 0; $s < self::N_STEPS; $s++) {
            for ($b = 0; $b < self::N_BRANCHES; $b++) {
                $subkeyItem = $k[self::N_BRANCHES * $s + $b];
                $bmul2 = $b << 1;
                $xb = $x[$bmul2];
                $xb1 = $x[$bmul2 + 1];

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
                $x[$bmul2] = $xb;
                $x[$bmul2 + 1] = $xb1;
            }

            $tmp = ((($x[0] ^ $x[1]) << 8) | (($x[0] ^ $x[1]) >> 8)) & 0xFFFF;
            $x[2] ^= $x[0] ^ $tmp;
            $x[3] ^= $x[1] ^ $tmp;

            $tmp = $x[0];
            $x[0] = $x[2];
            $x[2] = $tmp;
            $tmp = $x[1];
            $x[1] = $x[3];
            $x[3] = $tmp;
        }

        for ($b = 0; $b < self::N_BRANCHES; $b++) {
            $bmul2 = $b << 1;
            $subkeyItem = $k[self::N_BRANCHES * self::N_STEPS];
            $x[$bmul2] ^= $subkeyItem[$bmul2];
            $x[$bmul2 + 1] ^= $subkeyItem[$bmul2 + 1];
        }

        return $this->serializer->serialize($x);
    }

    public function decrypt(string $id): int
    {
        $x = $this->serializer->deserialize($id);
        $k = $this->subkeys;

        for ($b = 0; $b < self::N_BRANCHES; $b++) {
            $bmul2 = $b << 1;
            $subkeyItem = $k[self::N_BRANCHES * self::N_STEPS];
            $x[$bmul2] ^= $subkeyItem[$bmul2];
            $x[$bmul2 + 1] ^= $subkeyItem[$bmul2 + 1];
        }

        for ($s = self::N_STEPS - 1; $s >= 0; $s--) {
            $tmp = $x[0];
            $x[0] = $x[2];
            $x[2] = $tmp;
            $tmp = $x[1];
            $x[1] = $x[3];
            $x[3] = $tmp;

            $tmp = ((($x[0] ^ $x[1]) << 8) | (($x[0] ^ $x[1]) >> 8)) & 0xFFFF;
            $x[2] ^= $x[0] ^ $tmp;
            $x[3] ^= $x[1] ^ $tmp;

            for ($b = 0; $b < self::N_BRANCHES; $b++) {
                $subkeyItem = $k[self::N_BRANCHES * $s + $b];
                $bmul2 = $b << 1;
                $xb = $x[$bmul2];
                $xb1 = $x[$bmul2 + 1];
                for ($r = self::ROUNDS_PER_STEP - 1; $r >= 0; $r--) {

                    $r2 = $xb1 ^ $xb;
                    $r2 = (($r2 << 14) | ($r2 >> 2)) & 0xFFFF;
                    $l2 = ($xb - $r2) & 0xFFFF;
                    $l2 = (($l2 << 7) | ($l2 >> 9)) & 0xFFFF;

                    $xb = $l2;
                    $xb1 = $r2;

                    $xb ^= $subkeyItem[2 * $r];
                    $xb1 ^= $subkeyItem[2 * $r + 1];
                }
                $x[$bmul2] = $xb;
                $x[$bmul2 + 1] = $xb1;
            }
        }

        $num = $x[3] << 48;
        $num |= $x[2] << 32;
        $num |= $x[1] << 16;
        $num |= $x[0];

        if ($num < 0) {
            throw new IdDecodeException('Decrypted ID is out of valid range');
        }

        return $num;
    }

    /**
     * @return array{0: int, 1: int} each half of the branch
     */
    private function ArxRound(int $leftHalfBranch, int $rightHalfBranch): array
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
    private function KPerm64128(array &$key, int $roundConstant): void
    {
        [$key[0], $key[1]] = $this->ArxRound($key[0], $key[1]);
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
            $subkeys[$c] = \array_slice($masterKey, 0, 2 * self::ROUNDS_PER_STEP);
            $this->KPerm64128($masterKey, $c + 1);
        }

        return $subkeys;
    }

    public function getSerializer(): SerializerContract
    {
        return $this->serializer;
    }
}
