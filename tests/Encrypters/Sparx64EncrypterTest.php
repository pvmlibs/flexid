<?php

declare(strict_types=1);

namespace Tests\Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\Serializers\Base64Serializer;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\CustomSerializer;
use Pvmlibs\FlexId\Serializers\HexSerializer;
use Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersBCMath;
use Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersGmp;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class Sparx64EncrypterTest extends TestCase
{
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;
    use HasEncrypterTesting;

    public function testEncryptDecryptWithCustomAlphabetBCMathOps(): void
    {
        $this->runBatchForSerializer(new CustomSerializer(new FullRangeIntegersBCMath()));
    }

    public function testEncryptDecryptWithBaseSerializer(): void
    {
        $this->runBatchForSerializer(new BaseSerializer());
    }

    public function testEncryptDecryptWithCustomAlphabetGMPOps(): void
    {
        $this->runBatchForSerializer(new CustomSerializer(new FullRangeIntegersGmp()));
    }

    public function testEncryptDecryptWithHexSerializer(): void
    {
        $this->runBatchForSerializer(new HexSerializer());
    }

    public function testEncryptDecryptWithBase64Serializer(): void
    {
        $this->runBatchForSerializer(new Base64Serializer());
    }

    private function runBatchForSerializer(SerializerContract $serializer): void
    {
        $secret = Sparx64Encrypter::generateSecret();

        $encrypter = new Sparx64Encrypter(secret: $secret, serializer: $serializer);

        $encrypted = [];
        $expected = $this->runBatch($encrypter, $encrypted);
        $this::assertCount($expected, $encrypted, 'There are duplicates');

        $encryptedNoAD = $encrypter->encrypt(100);
        $encryptedWithAD = $encrypter->encrypt(100, 'abc');
        $this::assertSame($encryptedNoAD, $encryptedWithAD); // This encrypter does not support associated data
    }

    public function testWrongSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Sparx64Encrypter(secret: 'asfdsd', serializer: new BaseSerializer());
    }

    public function testEvenCharsDistributionHexSerializer(): void
    {
        $encrypter = new Sparx64Encrypter(secret: 'rCl29//aZ51LjLQZKUbMUA==', serializer: new HexSerializer());

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encrypter->encrypt(\random_int(0, PHP_INT_MAX), '');
        }
        $maxDeviations = $this->getMaxDeviation($ids, (new HexSerializer())->getAlphabet());
        // should be close to random, max deviation 2 times as random one from mean
        $this::assertLessThan($maxDeviations['random'] * 2, $maxDeviations['real']);
    }

    public function testBackwardCompatibility(): void
    {
        $encrypter = new Sparx64Encrypter(secret: 'rCl29//aZ51LjLQZKUbMUA==', serializer: new BaseSerializer());
        $this->validateBackwardCompatibility(fn (int $id): string => $encrypter->encrypt($id), PHP_INT_MIN, PHP_INT_MAX, 'Sparx64Encrypter');
    }
}
