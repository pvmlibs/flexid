<?php

declare(strict_types=1);

namespace Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Contracts\HMACContract;
use Pvmlibs\FlexId\Contracts\SerializerContract;
use Pvmlibs\FlexId\Encrypters\Sparx128Encrypter;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\HMAC\Blake2HMACSodiumExt;
use Pvmlibs\FlexId\HMAC\Sha256HMAC;
use Pvmlibs\FlexId\HMAC\SiphashHMAC;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\HexSerializer;
use Pvmlibs\FlexId\Tools\Alphabets;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\Encrypters\HasEncrypterTesting;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class Sparx128EncrypterTest extends TestCase
{
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;
    use HasEncrypterTesting;

    public function testEncryptDecryptWithBase64EncodingSipHash(): void
    {
        $key = KeyGen::generate();
        $hmac = new SiphashHMAC($key);
        $this->runBatchForEncoding(secret: $key, serializer: new BaseSerializer(), hmac: $hmac);
    }

    public function testEncryptDecryptWithHexBlake2b(): void
    {
        $key = KeyGen::generate();
        $hmac = new Blake2HMACSodiumExt($key);
        $this->runBatchForEncoding(secret: $key, serializer: new BaseSerializer(), hmac: $hmac);
    }

    public function testEncryptDecryptWithHexSha256(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $this->runBatchForEncoding(secret: $key, serializer: new BaseSerializer(), hmac: $hmac);
    }

    public function testInvalidSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: new BaseSerializer(), separator: '--');
    }

    public function testExcludedSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: new BaseSerializer(Alphabets::BASE32_SAFE_ALPHABET), separator: Alphabets::BASE32_SAFE_ALPHABET[0]);
    }

    public function testTooLongCiphertextDecrypt(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $encrypter = new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: new BaseSerializer());
        $this::expectException(IdDecodeException::class);
        $encrypter->decrypt('rdjdhfger9jfdglksdf34rlkmsdfpwjfs');
    }

    public function testEmptyCiphertextDecrypt(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $encrypter = new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: new BaseSerializer());
        $this::expectException(IdDecodeException::class);
        $encrypter->decrypt('');
    }

    public function testRandomCiphertextDecrypt(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $encrypter = new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: new BaseSerializer());

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
                $key = KeyGen::generate();
                $hmac = new Sha256HMAC($key);
                $encrypter = new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: new BaseSerializer());
                $ciphertext = $encrypter->encrypt($i);
                $ciphertextAltered = \str_shuffle($ciphertext);

                $encrypter->decrypt($ciphertextAltered);
            } catch (IdBadSignException|IdDecodeException $e) {
                $counter++;
                continue;
            }
            $this::fail("Decrypted bad ciphertext: {$ciphertextAltered}, original: {$ciphertext}");
        }
        $this::assertSame(1000, $counter);
    }

    private function runBatchForEncoding(string $secret, SerializerContract $serializer, HMACContract $hmac): void
    {
        $encrypter = new Sparx128Encrypter(base64secret: $secret, hmac: $hmac, serializer: $serializer);
        $encrypted = [];
        $this->runBatchWithAd($encrypter, $encrypted);
        $ad = \random_bytes(8);
        $expected = $this->runBatchWithAd($encrypter, $encrypted, $ad);
        $this::assertCount($expected, $encrypted, 'There are duplicates');

        $encryptedNoAD = $encrypter->encrypt(100);
        $encryptedWithAD = $encrypter->encrypt(100, 'abc');
        $this::assertNotSame($encryptedNoAD, $encryptedWithAD);
        $decryptedNoAD = $encrypter->decrypt($encryptedNoAD);
        $decryptedWithAD = $encrypter->decrypt($encryptedWithAD, 'abc');

        $this::assertSame($decryptedNoAD, $decryptedWithAD);
        $this::assertSame(100, $decryptedNoAD);
        $this::assertSame(100, $decryptedWithAD);

        $this::assertSame($serializer->getMaxEncodedLength() * 2, $encrypter->maxOutputLength());

        $this::expectException(IdBadSignException::class);
        $encrypter->decrypt($encryptedWithAD);
    }

    public function testWrongSecret(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $this->expectException(\InvalidArgumentException::class);
        new Sparx128Encrypter(base64secret: 'asfdsd', hmac: $hmac, serializer: new BaseSerializer());
    }

    public function testEvenCharsDistributionHex(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $serializer = new HexSerializer();
        $encrypter = new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: $serializer);

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encrypter->encrypt(\random_int(0, PHP_INT_MAX));
        }
        $maxDeviations = $this->getMaxDeviation($ids, $serializer->getAlphabet());
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

    public function testBackwardCompatibility(): void
    {
        $key = 'HtPA2DA8cy2gRUC4h+tKnKIjUt5xuLJzkmKc3MtwZpc=';
        $hmac = new Sha256HMAC($key);
        $encrypter = new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: new BaseSerializer());
        $this->validateBackwardCompatibility(fn (int $id): string => $encrypter->encrypt($id), PHP_INT_MIN, PHP_INT_MAX, 'Sparx128Encrypter');
    }
}
