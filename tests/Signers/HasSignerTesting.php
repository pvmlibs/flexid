<?php

declare(strict_types=1);

namespace Tests\Signers;

use Pvmlibs\FlexId\Contracts\SignerContract;

trait HasSignerTesting
{
    private function validateSignAndVerify(SignerContract $signer, int $range = 10000, int $idLengthFrom = 8, int $idLengthTo = 16): void
    {
        $signedIds = [];
        if ($idLengthFrom <= 1) {
            for ($i = 0; $i < 10; $i++) {
                $id = (string) $i;
                $signed = $signer->getSignedId($id);
                $signedIds[$signed] = $signed;
                $this::assertSame($id, $signer->getIdFromSigned($signed));
            }
        }
        if ($idLengthFrom <= 2) {
            for ($i = 10; $i < 99; $i++) {
                $id = (string) $i;
                $signed = $signer->getSignedId($id);
                $signedIds[$signed] = $signed;
                $this::assertSame($id, $signer->getIdFromSigned($signed));
            }
        }
        if ($idLengthFrom <= 3) {
            for ($i = 100; $i < 199; $i++) {
                $id = (string) $i;
                $signed = $signer->getSignedId($id);
                $signedIds[$signed] = $signed;
                $this::assertSame($id, $signer->getIdFromSigned($signed));
            }
        }
        $count = $expected = count($signedIds);
        if ($idLengthFrom > 3) {
            $bytesFrom = max(1, \intdiv($idLengthFrom, 2));
            $bytesTo = max(1, \intdiv($idLengthTo, 2));
            $incrementHashIdFromSign = hash_init('sha256');
            $incrementHashIdToSign = hash_init('sha256');

            for ($i = $count; $i < $range - $count; $i++) {
                $randomString = bin2hex(\random_bytes(\random_int($bytesFrom, $bytesTo)));

                $signed = $signer->getSignedId($randomString);
                $signedIds[$signed] = $signed;
                $expected++;

                hash_update($incrementHashIdFromSign, $signer->getIdFromSigned($signed));
                hash_update($incrementHashIdToSign, $randomString);
            }
            $this::assertSame(hash_final($incrementHashIdFromSign), hash_final($incrementHashIdToSign));
        }

        $this::assertSame($expected, \count($signedIds), 'There are duplicates');

        $signedNoAD = $signer->getSignedId('abcdef');
        $signedWithAD = $signer->getSignedId('abcdef', 'ad');
        if ($signer->maxOutputLength() > 2) {
            $this::assertNotSame($signedNoAD, $signedWithAD);
        }
    }
}
