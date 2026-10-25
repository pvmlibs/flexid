<?php

declare(strict_types=1);

namespace Tests\Encrypters\Ciphers;

use Pvmlibs\FlexId\Contracts\Cipher64Contract;

trait HasCipher64Testing
{
    private function runBatch(Cipher64Contract $cipher): void
    {
        $encryptedIds = [];
        $expected = 0;
        $incrementHashDecrypted = \hash_init('sha3-512');
        $incrementHashToEncrypt = \hash_init('sha3-512');

        // linear
        for ($i = 0; $i < 100; $i++) {
            $idEncrypted = $cipher->encrypt($i);
            $encryptedIds[$idEncrypted] = $idEncrypted;
            $expected++;
            \hash_update($incrementHashDecrypted, (string) $cipher->decrypt($idEncrypted));
            \hash_update($incrementHashToEncrypt, (string) $i);
        }

        // test random id for rest int range range
        for ($i = 0; $i < 5000; $i++) {
            $id = \random_int(1001, PHP_INT_MAX - 1);
            $idEncrypted = $cipher->encrypt($id);
            $encryptedIds[$idEncrypted] = $idEncrypted;
            $expected++;

            \hash_update($incrementHashDecrypted, (string) $cipher->decrypt($idEncrypted));
            \hash_update($incrementHashToEncrypt, (string) $id);

            // negatives
            $idEncrypted = $cipher->encrypt(-$id);
            $encryptedIds[$idEncrypted] = $idEncrypted;
            $expected++;
            \hash_update($incrementHashDecrypted, (string) $cipher->decrypt($idEncrypted));
            \hash_update($incrementHashToEncrypt, (string) -$id);
        }

        // corner cases
        $encrypted = $cipher->encrypt(PHP_INT_MAX);
        $encryptedIds[$encrypted] = $encrypted;
        $expected++;
        \hash_update($incrementHashDecrypted, (string) $cipher->decrypt($encrypted));
        \hash_update($incrementHashToEncrypt, (string) PHP_INT_MAX);

        $encrypted = $cipher->encrypt(PHP_INT_MIN);
        $encryptedIds[$encrypted] = $encrypted;
        $expected++;
        \hash_update($incrementHashDecrypted, (string) $cipher->decrypt($encrypted));
        \hash_update($incrementHashToEncrypt, (string) PHP_INT_MIN);

        $encrypted = $cipher->encrypt(-1);
        $encryptedIds[$encrypted] = $encrypted;
        $expected++;
        \hash_update($incrementHashDecrypted, (string) $cipher->decrypt($encrypted));
        \hash_update($incrementHashToEncrypt, '-1');

        $this::assertSame(\hash_final($incrementHashDecrypted), \hash_final($incrementHashToEncrypt));
        $this::assertSame($expected, count($encryptedIds), 'There are duplicates');
    }

    private function runDeterministicBatch(Cipher64Contract $cipher): string
    {
        $incrementHashEncrypted = \hash_init('sha3-512');

        // linear
        for ($id = 1; $id < 999; $id++) {
            \hash_update($incrementHashEncrypted, (string) $cipher->encrypt($id));
            \hash_update($incrementHashEncrypted, (string) $cipher->encrypt(-$id));
        }

        // test random id for rest int range range
        $step = \intdiv(PHP_INT_MAX, 1000);
        for ($id = 1000; $id < PHP_INT_MAX; $id += $step) {
            \hash_update($incrementHashEncrypted, (string) $cipher->encrypt($id));
            // negatives
            \hash_update($incrementHashEncrypted, (string) $cipher->encrypt(-$id));
        }

        // corner cases
        \hash_update($incrementHashEncrypted, (string) $cipher->encrypt(PHP_INT_MAX));
        \hash_update($incrementHashEncrypted, (string) $cipher->encrypt(PHP_INT_MIN));
        \hash_update($incrementHashEncrypted, (string) $cipher->encrypt(0));

        return \hash_final($incrementHashEncrypted);
    }
}
