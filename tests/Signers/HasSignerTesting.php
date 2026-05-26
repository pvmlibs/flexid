<?php

declare(strict_types=1);

namespace Tests\Signers;

use Pvmlibs\FlexId\Signers\SignerContract;

trait HasSignerTesting
{
    private function validateSignAndVerify(SignerContract $signer, int $range = 10000, int $idLengthFrom = 8, int $idLengthTo = 16): void
    {
        $signedIds = [];
        if ($idLengthFrom <= 1) {
            for ($i = 0; $i < 10; $i++) {
                $id = (string) $i;
                $signedIds[$i] = $signer->getSignedId($id);
                $this::assertSame($id, $signer->getIdFromSigned($signedIds[$i]), $signer->getIdFromSigned($signedIds[$i]));
            }
        }
        if ($idLengthFrom <= 2) {
            for ($i = 10; $i < 99; $i++) {
                $id = (string) $i;
                $signedIds[$i] = $signer->getSignedId($id);
                $this::assertSame($id, $signer->getIdFromSigned($signedIds[$i]), $signer->getIdFromSigned($signedIds[$i]));
            }
        }
        if ($idLengthFrom <= 3) {
            for ($i = 100; $i < 199; $i++) {
                $id = (string) $i;
                $signedIds[$i] = $signer->getSignedId($id);
                $this::assertSame($id, $signer->getIdFromSigned($signedIds[$i]), $signer->getIdFromSigned($signedIds[$i]));
            }
        }
        $count = count($signedIds);
        if ($idLengthFrom > 3) {
            $bytesFrom = max(1, \intdiv($idLengthFrom, 2));
            $bytesTo = max(1, \intdiv($idLengthTo, 2));
            $incrementHashIdFromSign = hash_init('sha256');
            $incrementHashIdToSign = hash_init('sha256');

            for ($i = $count; $i < $range - $count; $i++) {
                $randomString = bin2hex(\random_bytes(\random_int($bytesFrom, $bytesTo)));

                $signedIds[$i] = $signer->getSignedId($randomString);

                hash_update($incrementHashIdFromSign, $signer->getIdFromSigned($signedIds[$i]));
                hash_update($incrementHashIdToSign, $randomString);
            }
            $this::assertSame(hash_final($incrementHashIdFromSign), hash_final($incrementHashIdToSign));
        }

        $this::assertCount(\count($signedIds), \array_unique($signedIds));
    }
}
