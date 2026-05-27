<?php

declare(strict_types=1);

namespace Tests\Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Serializers\FixedLengthSerializer;
use Pvmlibs\FlexId\Serializers\GMPSerializer;
use Pvmlibs\FlexId\Serializers\NativeSerializer;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasIdCharDistributionTesting;

/**
 * @internal
 */
final class Sparx64EncrypterTest extends TestCase
{
    use HasIdCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testEncryptDecryptWithBCMathSerializer(): void
    {
        $secret = Sparx64Encrypter::generateSecret();
        $this->runBatch(new Sparx64Encrypter(secret: $secret, serializer: new BCMathSerializer()));
    }

    public function testEncryptDecryptWithNativeSerializer(): void
    {
        $secret = Sparx64Encrypter::generateSecret();
        $this->runBatch(new Sparx64Encrypter(secret: $secret, serializer: new NativeSerializer()));
    }

    public function testEncryptDecryptWithGMPSerializer(): void
    {
        $secret = Sparx64Encrypter::generateSecret();
        $this->runBatch(new Sparx64Encrypter(secret: $secret, serializer: new GMPSerializer()));
    }

    public function testEncryptDecryptWithFixedLengthSerializer(): void
    {
        $secret = Sparx64Encrypter::generateSecret();
        $this->runBatch(new Sparx64Encrypter(secret: $secret, serializer: new FixedLengthSerializer()));
    }

    private function runBatch(Sparx64Encrypter $encrypter): void
    {
        $encryptedIds = [];
        for ($i = 0; $i < 200; $i++) {
            $encryptedIds[] = $encrypter->encrypt($i);
            $this::assertSame($i, $encrypter->decrypt($encryptedIds[$i]));
        }
        $this::assertCount(\count($encryptedIds), \array_unique($encryptedIds));

        $incrementHashDecrypted = hash_init('sha256');
        $incrementHashToEncrypt = hash_init('sha256');

        // test random id from whole range
        $encryptedIds = [];
        for ($i = 0; $i < 2000; $i++) {
            $id = \random_int(1001, PHP_INT_MAX - 1);
            $encryptedIds[] = ($idEncrypted = $encrypter->encrypt($id));
            hash_update($incrementHashDecrypted, (string) $encrypter->decrypt($idEncrypted));
            hash_update($incrementHashToEncrypt, (string) $id);
        }

        $this::assertSame(\hash_final($incrementHashDecrypted), \hash_final($incrementHashToEncrypt));

        $this::assertCount(\count($encryptedIds), \array_unique($encryptedIds));

        $encoded = $encrypter->encrypt(PHP_INT_MAX);
        $this::assertSame(PHP_INT_MAX, $encrypter->decrypt($encoded));
    }

    public function testWrongSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Sparx64Encrypter(secret: 'asfdsd', serializer: new BCMathSerializer());
    }

    public function testEncryptBelowRange(): void
    {
        $secret = Sparx64Encrypter::generateSecret();
        $encrypter = new Sparx64Encrypter(secret: $secret, serializer: new BCMathSerializer());
        $this->expectException(IdEncodeException::class);
        $encrypter->encrypt(-1);
    }

    public function testEvenCharsDistribution(): void
    {
        $encrypter = new Sparx64Encrypter(secret: 'rCl29//aZ51LjLQZKUbMUA==', serializer: new FixedLengthSerializer());

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encrypter->encrypt(\random_int(0, PHP_INT_MAX));
        }
        $maxDeviations = $this->getMaxDeviation($ids, $encrypter->getSerializer()->getAlphabet());
        // should be close to random, max deviation 2 times as random one from mean
        $this::assertLessThan($maxDeviations['random'] * 2, $maxDeviations['real']);
    }

    public function testBackwardCompatibility(): void
    {
        $encrypter = new Sparx64Encrypter(secret: 'rCl29//aZ51LjLQZKUbMUA==', serializer: new BCMathSerializer());
        $this->validateBackwardCompatibility(fn (int $id): string => $encrypter->encrypt($id), PHP_INT_MAX, 'Sparx64Encrypter');
    }
}
