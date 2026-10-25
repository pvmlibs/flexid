<?php

declare(strict_types=1);

namespace Tests\Encrypters\Ciphers;

use Pvmlibs\FlexId\Contracts\Cipher128Contract;

trait HasCipher128Testing
{
    private function runBatch(Cipher128Contract $cipher): int
    {
        $incrementHashDecrypted = \hash_init('sha3-512');
        $incrementHashToEncrypt = \hash_init('sha3-512');
        $expected = 0;
        $encryptedIdsLows = [];
        $encryptedIdsHighs = [];

        // linear
        for ($i = 0; $i < 100; $i++) {
            $idEncrypted = $cipher->encrypt($i, $i - 10);
            $encryptedIdsLows[$idEncrypted[0]] = $idEncrypted[0];
            $encryptedIdsHighs[$idEncrypted[1]] = $idEncrypted[1];
            $expected++;

            \hash_update($incrementHashDecrypted, serialize($cipher->decrypt($idEncrypted[0], $idEncrypted[1])));
            \hash_update($incrementHashToEncrypt, serialize([$i, $i - 10]));
        }

        // test random id for rest int range range
        for ($i = 0; $i < 5000; $i++) {
            $idLow = \random_int(1001, PHP_INT_MAX - 1);
            $idHigh = PHP_INT_MAX - $idLow;
            $idEncrypted = $cipher->encrypt($idLow, $idHigh);
            $encryptedIdsLows[$idEncrypted[0]] = $idEncrypted[0];
            $encryptedIdsHighs[$idEncrypted[1]] = $idEncrypted[1];
            $expected++;
            \hash_update($incrementHashDecrypted, serialize($cipher->decrypt($idEncrypted[0], $idEncrypted[1])));
            \hash_update($incrementHashToEncrypt, serialize([$idLow, $idHigh]));

            // negatives
            $idLow = -$idLow;
            $idHigh = -$idHigh;
            $idEncrypted = $cipher->encrypt($idLow, $idHigh);
            $encryptedIdsLows[$idEncrypted[0]] = $idEncrypted[0];
            $encryptedIdsHighs[$idEncrypted[1]] = $idEncrypted[1];
            $expected++;
            \hash_update($incrementHashDecrypted, serialize($cipher->decrypt($idEncrypted[0], $idEncrypted[1])));
            \hash_update($incrementHashToEncrypt, serialize([$idLow, $idHigh]));
        }

        // corner cases
        $idLow = PHP_INT_MAX;
        $idHigh = PHP_INT_MAX;
        $idEncrypted = $cipher->encrypt($idLow, $idHigh);
        $encryptedIdsLows[$idEncrypted[0]] = $idEncrypted[0];
        $encryptedIdsHighs[$idEncrypted[1]] = $idEncrypted[1];
        $expected++;
        \hash_update($incrementHashDecrypted, serialize($cipher->decrypt($idEncrypted[0], $idEncrypted[1])));
        \hash_update($incrementHashToEncrypt, serialize([$idLow, $idHigh]));

        $idLow = PHP_INT_MIN;
        $idHigh = PHP_INT_MIN;
        $idEncrypted = $cipher->encrypt($idLow, $idHigh);
        $encryptedIdsLows[$idEncrypted[0]] = $idEncrypted[0];
        $encryptedIdsHighs[$idEncrypted[1]] = $idEncrypted[1];
        $expected++;
        \hash_update($incrementHashDecrypted, serialize($cipher->decrypt($idEncrypted[0], $idEncrypted[1])));
        \hash_update($incrementHashToEncrypt, serialize([$idLow, $idHigh]));

        $idLow = -1;
        $idHigh = -1;
        $idEncrypted = $cipher->encrypt($idLow, $idHigh);
        $encryptedIdsLows[$idEncrypted[0]] = $idEncrypted[0];
        $encryptedIdsHighs[$idEncrypted[1]] = $idEncrypted[1];
        $expected++;
        \hash_update($incrementHashDecrypted, serialize($cipher->decrypt($idEncrypted[0], $idEncrypted[1])));
        \hash_update($incrementHashToEncrypt, serialize([$idLow, $idHigh]));

        $this::assertSame(\hash_final($incrementHashDecrypted), \hash_final($incrementHashToEncrypt));
        $this::assertSame($expected, count($encryptedIdsLows), 'There are duplicates');
        $this::assertSame($expected, count($encryptedIdsHighs), 'There are duplicates');

        return $expected;
    }

    private function runDeterministicBatch(Cipher128Contract $cipher): string
    {
        $incrementHashEncrypted = \hash_init('sha3-512');

        // linear
        for ($id = 1; $id < 999; $id++) {
            \hash_update($incrementHashEncrypted, serialize($cipher->encrypt($id, $id)));
            \hash_update($incrementHashEncrypted, serialize($cipher->encrypt(-$id, -$id)));
        }

        $i = 0;
        // test random id for rest int range range
        $step = \intdiv(PHP_INT_MAX, 1000);
        for ($id = 1000; $id < PHP_INT_MAX; $id += $step) {
            \hash_update($incrementHashEncrypted, serialize($cipher->encrypt($id, $id)));
            // negatives
            \hash_update($incrementHashEncrypted, serialize($cipher->encrypt(-$id, -$id)));
        }

        // corner cases
        \hash_update($incrementHashEncrypted, serialize($cipher->encrypt(PHP_INT_MAX, PHP_INT_MAX)));
        \hash_update($incrementHashEncrypted, serialize($cipher->encrypt(PHP_INT_MIN, PHP_INT_MIN)));
        \hash_update($incrementHashEncrypted, serialize($cipher->encrypt(0, 0)));

        return \hash_final($incrementHashEncrypted);
    }
}
