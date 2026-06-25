<?php

declare(strict_types=1);

namespace Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\XChaCha20Encrypter;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Tests\Encrypters\HasEncrypterTesting;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class ChaCha20EncrypterTest extends TestCase
{
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;
    use HasEncrypterTesting;

    public function testEncryptDecryptWithBase64Encoding(): void
    {
        $this->runBatchForEncoding(true);
    }

    public function testEncryptDecryptWithHexEncoding(): void
    {
        $this->runBatchForEncoding(false);
    }

    private function runBatchForEncoding(bool $useBase64): void
    {
        $secret = XChaCha20Encrypter::generateSecret();

        $encrypter = new XChaCha20Encrypter(secret: $secret, base64Encode: $useBase64);
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

        if ($useBase64) {
            $this::assertSame(64, $encrypter->maxOutputLength());
        } else {
            $this::assertSame(96, $encrypter->maxOutputLength());
        }

        $this::expectException(IdDecryptException::class);
        $encrypter->decrypt($encryptedWithAD);
    }

    public function testTooLongCiphertextDecrypt(): void
    {
        $encrypter = new XChaCha20Encrypter(XChaCha20Encrypter::generateSecret());
        $this::expectException(IdDecodeException::class);
        $encrypter->decrypt('rdjdhfger9jfdglksdf34rlkmsdfpwjfsrdjdhfger9jfdglksdf34rlkmsdfpwjfs');
    }

    public function testEmptyCiphertextDecrypt(): void
    {
        $encrypter = new XChaCha20Encrypter(XChaCha20Encrypter::generateSecret());
        $this::expectException(IdDecodeException::class);
        $encrypter->decrypt('');
    }

    public function testRandomCiphertextDecrypt(): void
    {
        $encrypter = new XChaCha20Encrypter(XChaCha20Encrypter::generateSecret());
        $counter = 0;
        for ($i = 0; $i < 1000; $i++) {
            try {
                $encrypter->decrypt(\random_bytes(random_int(1, 100)));
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
                $key = XChaCha20Encrypter::generateSecret();
                $encrypter = new XChaCha20Encrypter($key);
                $ciphertext = $encrypter->encrypt($i);
                $ciphertextAltered = str_shuffle($ciphertext);

                $encrypter->decrypt($ciphertextAltered);
            } catch (IdDecryptException $e) {
                $counter++;
                continue;
            }
            $key = base64_encode($key);
            $this::fail("Decrypted bad ciphertext: {$ciphertextAltered}, original: {$ciphertext}, key: {$key}");
        }
        $this::assertSame(1000, $counter);
    }

    public function testWrongSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new XChaCha20Encrypter(secret: 'asfdsd');
    }

    public function testEvenCharsDistributionBase64(): void
    {
        $secret = XChaCha20Encrypter::generateSecret();
        $encrypter = new XChaCha20Encrypter(secret: $secret, base64Encode: true);

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encrypter->encrypt(\random_int(0, PHP_INT_MAX));
        }
        $maxDeviations = $this->getMaxDeviation($ids, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_');
        // should be close to random, max deviation 2 times as random one from mean
        $this::assertLessThan($maxDeviations['random'] * 2, $maxDeviations['real']);
    }

    public function testEvenCharsDistributionHex(): void
    {
        $secret = XChaCha20Encrypter::generateSecret();
        $encrypter = new XChaCha20Encrypter(secret: $secret, base64Encode: false);

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encrypter->encrypt(\random_int(0, PHP_INT_MAX));
        }
        $maxDeviations = $this->getMaxDeviation($ids, '0123456789abcdef');
        // should be close to random, max deviation 3 times as random one from mean
        $this::assertLessThan(
            $maxDeviations['random'] * 3,
            $maxDeviations['real'],
            sprintf(
                'Max deviation of %s (%f) is above limit %f, mean %f',
                $maxDeviations['mostFrequentChar'],
                $maxDeviations['real'],
                $maxDeviations['random'] * 3,
                $maxDeviations['mean'],
            ),
        );
    }
}
