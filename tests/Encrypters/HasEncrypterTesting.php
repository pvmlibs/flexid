<?php

declare(strict_types=1);

namespace Tests\Encrypters;

use Pvmlibs\FlexId\Contracts\EncrypterContract;

trait HasEncrypterTesting
{
    /**
     * @param array<string> $encryptedIds
     */
    private function runBatch(EncrypterContract $encrypter, array &$encryptedIds, string $ad = ''): void
    {
        // merging with existing array
        $expected = count($encryptedIds);

        $incrementHashDecrypted = \hash_init('sha3-512');
        $incrementHashToEncrypt = \hash_init('sha3-512');

        // linear
        for ($i = 1; $i < 10; $i++) {
            $idEncrypted = $encrypter->encrypt($i, $ad);
            $encryptedIds[$idEncrypted] = $idEncrypted;
            $expected++;
            \hash_update($incrementHashDecrypted, (string) $encrypter->decrypt($idEncrypted, $ad));
            \hash_update($incrementHashToEncrypt, (string) $i);

            // negatives
            $idEncrypted = $encrypter->encrypt(-$i, $ad);
            $encryptedIds[$idEncrypted] = $idEncrypted;

            $expected++;
            \hash_update($incrementHashDecrypted, (string) $encrypter->decrypt($idEncrypted, $ad));
            \hash_update($incrementHashToEncrypt, (string) -$i);
        }

        // test random id for rest int range range
        for ($i = 0; $i < 5000; $i++) {
            $id = \random_int(1001, PHP_INT_MAX - 1);
            $idEncrypted = $encrypter->encrypt($id, $ad);
            $encryptedIds[$idEncrypted] = $idEncrypted;
            $expected++;

            \hash_update($incrementHashDecrypted, (string) $encrypter->decrypt($idEncrypted, $ad));
            \hash_update($incrementHashToEncrypt, (string) $id);

            // negatives
            $idEncrypted = $encrypter->encrypt(-$id, $ad);
            $encryptedIds[$idEncrypted] = $idEncrypted;

            $expected++;
            \hash_update($incrementHashDecrypted, (string) $encrypter->decrypt($idEncrypted, $ad));
            \hash_update($incrementHashToEncrypt, (string) -$id);
        }

        // corner cases
        $encrypted = $encrypter->encrypt(PHP_INT_MAX, $ad);
        $encryptedIds[$encrypted] = $encrypted;
        $expected++;
        \hash_update($incrementHashDecrypted, (string) $encrypter->decrypt($encrypted, $ad));
        \hash_update($incrementHashToEncrypt, (string) PHP_INT_MAX);

        $encrypted = $encrypter->encrypt(PHP_INT_MIN, $ad);
        $encryptedIds[$encrypted] = $encrypted;
        $expected++;
        \hash_update($incrementHashDecrypted, (string) $encrypter->decrypt($encrypted, $ad));
        \hash_update($incrementHashToEncrypt, (string) PHP_INT_MIN);

        $encrypted = $encrypter->encrypt(0, $ad);
        $encryptedIds[$encrypted] = $encrypted;
        $expected++;
        \hash_update($incrementHashDecrypted, (string) $encrypter->decrypt($encrypted, $ad));
        \hash_update($incrementHashToEncrypt, '0');

        $this::assertSame(\hash_final($incrementHashDecrypted), \hash_final($incrementHashToEncrypt));
        $this::assertCount($expected, $encryptedIds);
    }

}
