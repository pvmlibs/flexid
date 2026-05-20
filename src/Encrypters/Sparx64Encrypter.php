<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Encrypters;

use Pvmlibs\FlexId\Encrypters\Serializers\SerializerContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

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

    /** @var array<int<0, 16>, list<int>> Subkeys for encryption/decryption. */
    private array $subkeys = [];

    /**
     * @param string $secret base64 encoded, 16 bytes secret
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $secret,
        private SerializerContract $serializer,
    ) {
        $decodedSecret = \base64_decode($secret, true);

        if ($decodedSecret === false || \strlen($decodedSecret) !== self::SECRET_SIZE) {
            throw new \InvalidArgumentException(\sprintf('Secret key must be %d bytes long.', self::SECRET_SIZE));
        }

        $masterKey = [];
        for ($i = 0; $i < 2 * self::K_SIZE; $i++) {
            $masterKey[$i] = (\ord($secret[2 * $i]) << 8) | \ord($secret[2 * $i + 1]);
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

        $mask = ((1 << 16) - 1);
        $x = [
            $id & $mask,
            ($id >> 16) & $mask,
            ($id >> 32) & $mask,
            ($id >> 48) & $mask,
        ];

        $k = $this->subkeys;
        for ($s = 0; $s < self::N_STEPS; $s++) {
            for ($b = 0; $b < self::N_BRANCHES; $b++) {
                for ($r = 0; $r < self::ROUNDS_PER_STEP; $r++) {
                    $x[2 * $b] ^= $k[self::N_BRANCHES * $s + $b][2 * $r];
                    $x[2 * $b + 1] ^= $k[self::N_BRANCHES * $s + $b][2 * $r + 1];

                    $l2 = (($x[2 * $b] << 9) | ($x[2 * $b] >> 7)) & 0xFFFF;
                    $l2 = ($l2 + $x[2 * $b + 1]) & 0xFFFF;
                    $r2 = (($x[2 * $b + 1] << 2) | ($x[2 * $b + 1] >> 14)) & 0xFFFF;
                    $r2 ^= $l2;

                    $x[2 * $b] = $l2;
                    $x[2 * $b + 1] = $r2;
                }
            }

            $tmp = ((($x[0] ^ $x[1]) << 8) | (($x[0] ^ $x[1]) >> 8)) & 0xFFFF;
            $x[2] ^= $x[0] ^ $tmp;
            $x[3] ^= $x[1] ^ $tmp;
            [$x[0], $x[2]] = [$x[2], $x[0]];
            [$x[1], $x[3]] = [$x[3], $x[1]];
        }

        for ($b = 0; $b < self::N_BRANCHES; $b++) {
            $x[2 * $b] ^= $k[self::N_BRANCHES * self::N_STEPS][2 * $b];
            $x[2 * $b + 1] ^= $k[self::N_BRANCHES * self::N_STEPS][2 * $b + 1];
        }

        return $this->serializer->serialize($x);
    }

    public function decrypt(string $id): int
    {
        $x = $this->serializer->deserialize($id);
        $k = $this->subkeys;

        for ($b = 0; $b < self::N_BRANCHES; $b++) {
            $x[2 * $b] ^= $k[self::N_BRANCHES * self::N_STEPS][2 * $b];
            $x[2 * $b + 1] ^= $k[self::N_BRANCHES * self::N_STEPS][2 * $b + 1];
        }

        for ($s = self::N_STEPS - 1; $s >= 0; $s--) {
            [$x[0], $x[2]] = [$x[2], $x[0]];
            [$x[1], $x[3]] = [$x[3], $x[1]];

            $tmp = ((($x[0] ^ $x[1]) << 8) | (($x[0] ^ $x[1]) >> 8)) & 0xFFFF;
            $x[2] ^= $x[0] ^ $tmp;
            $x[3] ^= $x[1] ^ $tmp;

            for ($b = 0; $b < self::N_BRANCHES; $b++) {
                for ($r = self::ROUNDS_PER_STEP - 1; $r >= 0; $r--) {

                    $r2 = $x[2 * $b + 1] ^ $x[2 * $b];
                    $r2 = (($r2 << 14) | ($r2 >> 2)) & 0xFFFF;
                    $l2 = ($x[2 * $b] - $r2) & 0xFFFF;
                    $l2 = (($l2 << 7) | ($l2 >> 9)) & 0xFFFF;

                    $x[2 * $b] = $l2;
                    $x[2 * $b + 1] = $r2;

                    $x[2 * $b] ^= $k[self::N_BRANCHES * $s + $b][2 * $r];
                    $x[2 * $b + 1] ^= $k[self::N_BRANCHES * $s + $b][2 * $r + 1];
                }
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
     * @param array<int<0, 7>, int> $key
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
     * @param array<int<0, 7>, int> $masterKey
     *
     * @return array<int<0, 16>, list<int>>
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

    public function getMaxEncryptedLength(): int
    {
        return $this->serializer->getMaxEncodedLength();
    }
}
