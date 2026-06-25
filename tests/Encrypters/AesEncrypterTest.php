<?php

declare(strict_types=1);

namespace Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Encrypters\AesEncrypter;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Tests\Encrypters\HasEncrypterTesting;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class AesEncrypterTest extends TestCase
{
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;
    use HasEncrypterTesting;

    public function testEncryptDecryptWithBase64EncodingSipHash(): void
    {
        $this->runBatchForEncoding(secret: AesEncrypter::generateSecret(), serializer: null, macHash: 'siphash-2-4');
    }

    public function testEncryptDecryptWithHexBlake2b(): void
    {
        $this->runBatchForEncoding(secret: AesEncrypter::generateSecret(), serializer: null, macHash: 'blake2b');
    }

    public function testEncryptDecryptWithHexSha256(): void
    {
        $this->runBatchForEncoding(secret: AesEncrypter::generateSecret(), serializer: null, macHash: 'sha256');
    }

    public function testEncryptDecryptWithSerializer(): void
    {
        $this->runBatchForEncoding(secret: AesEncrypter::generateSecret(), serializer: new BaseSerializer(), macHash: 'siphash-2-4');
    }

    public function testEncryptDecryptKey192bit(): void
    {
        $this->runBatchForEncoding(secret: AesEncrypter::generateSecret(24), serializer: null, macHash: 'siphash-2-4');
    }

    public function testEncryptDecryptKey128bit(): void
    {
        $this->runBatchForEncoding(secret: AesEncrypter::generateSecret(16), serializer: null, macHash: 'siphash-2-4');
    }

    public function testUnsupportedMacHash(): void
    {
        $this::expectException(\InvalidArgumentException::class);
        new AesEncrypter(AesEncrypter::generateSecret(), 'xxh64');
    }

    public function testInvalidKeyLength(): void
    {
        $this::expectException(\InvalidArgumentException::class);
        new AesEncrypter('dshwe');
    }

    public function testInvalidSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AesEncrypter(secret: AesEncrypter::generateSecret(), separator: '--');
    }

    public function testExcludedSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AesEncrypter(secret: AesEncrypter::generateSecret(), serializer: new BaseSerializer(), separator: BaseSerializer::ALPHABET[0]);
    }

    public function testTooLongCiphertextDecrypt(): void
    {
        $encrypter = new AesEncrypter(AesEncrypter::generateSecret());
        $this::expectException(IdDecodeException::class);
        $encrypter->decrypt('rdjdhfger9jfdglksdf34rlkmsdfpwjfs');
    }

    public function testEmptyCiphertextDecrypt(): void
    {
        $encrypter = new AesEncrypter(AesEncrypter::generateSecret());
        $this::expectException(IdDecodeException::class);
        $encrypter->decrypt('');
    }

    public function testRandomCiphertextDecrypt(): void
    {
        $encrypter = new AesEncrypter(AesEncrypter::generateSecret());
        $counter = 0;
        for ($i = 0; $i < 1000; $i++) {
            try {
                $encrypter->decrypt(\random_bytes(\random_int(1, 100)));
            } catch (IdDecodeException|IdDecryptException $e) {
                $counter++;
            }
        }
        $this::assertSame(1000, $counter);
    }

    public function testAlterCiphertextDecrypt(): void
    {
        $counter = 0;
        for ($i = 0; $i < 1000; $i++) {
            try {
                $key = AesEncrypter::generateSecret();
                $encrypter = new AesEncrypter($key);
                $ciphertext = $encrypter->encrypt($i);
                $ciphertextAltered = \str_shuffle($ciphertext);

                $encrypter->decrypt($ciphertextAltered);
            } catch (IdBadSignException $e) {
                $counter++;
                continue;
            }
            $key = base64_encode($key);
            $this::fail("Decrypted bad ciphertext: {$ciphertextAltered}, original: {$ciphertext}, key: {$key}");
        }
        $this::assertSame(1000, $counter);
    }

    private function runBatchForEncoding(string $secret, ?SerializerContract $serializer, string $macHash): void
    {
        $encrypter = new AesEncrypter(secret: $secret, serializer: $serializer, macHash: $macHash);
        $encrypted = [];
        $this->runBatch($encrypter, $encrypted);
        $ad = \random_bytes(8);
        $expected = $this->runBatch($encrypter, $encrypted, $ad);
        $this::assertCount($expected, $encrypted, 'There are duplicates');

        $encryptedNoAD = $encrypter->encrypt(100);
        $encryptedWithAD = $encrypter->encrypt(100, 'abc');
        $this::assertNotSame($encryptedNoAD, $encryptedWithAD);
        $decryptedNoAD = $encrypter->decrypt($encryptedNoAD);
        $decryptedWithAD = $encrypter->decrypt($encryptedWithAD, 'abc');

        $this::assertSame($decryptedNoAD, $decryptedWithAD);
        $this::assertSame(100, $decryptedNoAD);
        $this::assertSame(100, $decryptedWithAD);

        if ($serializer !== null) {
            $this::assertSame($serializer->getMaxEncodedLength() * 2, $encrypter->maxOutputLength());
        } else {
            $this::assertSame(32, $encrypter->maxOutputLength());
        }
        $this::expectException(IdBadSignException::class);
        $encrypter->decrypt($encryptedWithAD);
    }

    public function testWrongSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AesEncrypter(secret: 'asfdsd');
    }

    public function testEvenCharsDistributionHex(): void
    {
        $secret = AesEncrypter::generateSecret();
        $encrypter = new AesEncrypter(secret: $secret);

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encrypter->encrypt(\random_int(0, PHP_INT_MAX));
        }
        $maxDeviations = $this->getMaxDeviation($ids, '0123456789abcdef');
        // should be close to random, max deviation 2 times as random one from mean
        $this::assertLessThan(
            $maxDeviations['random'] * 2,
            $maxDeviations['real'],
            sprintf(
                'Max deviation of %s (%f) is above limit %f, mean %f',
                $maxDeviations['mostFrequentChar'],
                $maxDeviations['real'],
                $maxDeviations['random'] * 2,
                $maxDeviations['mean'],
            ),
        );
    }

    public function testBackwardCompatibility(): void
    {
        $encrypter = new AesEncrypter(secret: 'HtPA2DA8cy2gRUC4h+tKnKIjUt5xuLJzkmKc3MtwZpc=');
        $this->validateBackwardCompatibility(fn (int $id): string => $encrypter->encrypt($id), PHP_INT_MIN, PHP_INT_MAX, 'AesEncrypter');
    }
}
