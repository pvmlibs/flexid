<?php

declare(strict_types=1);

namespace Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Sparx128Encrypter;
use Pvmlibs\FlexId\Encrypters\Sparx128TurboEncrypter;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\HMAC\SiphashHMAC;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Tools\Alphabets;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\Encrypters\HasEncrypterTesting;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class Sparx128TurboEncrypterTest extends TestCase
{
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;
    use HasEncrypterTesting;

    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testEncryptDecryptWithBase64EncodingSipHash(): void
    {
        $encrypter = new Sparx128TurboEncrypter(base64secret: KeyGen::generate());
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

        $this::assertSame(22, $encrypter->maxOutputLength());

        $this::expectException(IdBadSignException::class);
        $encrypter->decrypt($encryptedWithAD);
    }

    public function testInvalidSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $key = KeyGen::generate();
        new Sparx128TurboEncrypter(base64secret: $key, separator: '--');
    }

    public function testExcludedSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $key = KeyGen::generate();
        new Sparx128TurboEncrypter(base64secret: $key, separator: Alphabets::CUSTOM_SEMI_SAFE[0]);
    }

    public function testTooLongCiphertextDecrypt(): void
    {
        $key = KeyGen::generate();
        $encrypter = new Sparx128TurboEncrypter(base64secret: $key);
        $this::expectException(IdDecodeException::class);
        $encrypter->decrypt('rdjdhfger9jfdglksdf34rlkmsdfpwjfs');
    }

    public function testEmptyCiphertextDecrypt(): void
    {
        $key = KeyGen::generate();
        $encrypter = new Sparx128TurboEncrypter(base64secret: $key);
        $this::expectException(IdDecodeException::class);
        $encrypter->decrypt('');
    }

    public function testRandomCiphertextDecrypt(): void
    {
        $key = KeyGen::generate();
        $encrypter = new Sparx128TurboEncrypter(base64secret: $key);

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
                $encrypter = new Sparx128TurboEncrypter(base64secret: $key);
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

    public function testWrongSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Sparx128TurboEncrypter(base64secret: 'asfdsd');
    }

    public function testWrongSalt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Sparx128TurboEncrypter(base64secret: KeyGen::generate(), hkdfSalt: 'dfhdg');
    }

    public function testWithSalt(): void
    {
        $key = KeyGen::generate();
        $encrypter = new Sparx128TurboEncrypter(base64secret: $key, hkdfSalt: KeyGen::generate());
        $encrypter2 = new Sparx128TurboEncrypter(base64secret: $key, hkdfSalt: KeyGen::generate());

        $this::assertNotSame($encrypter->encrypt(123456), $encrypter2->encrypt(123456));
    }

    public function testInteroperabilityCustomSerializer(): void
    {
        $key = 'HtPA2DA8cy2gRUC4h+tKnKIjUt5xuLJzkmKc3MtwZpc=';
        $encrypterTurbo = new Sparx128TurboEncrypter(base64secret: $key, alphabet: Alphabets::BASE32_SAFE_ALPHABET);
        $hmac = new SiphashHMAC($key);
        $encrypterDefault = new Sparx128Encrypter(base64secret: $key, hmac: $hmac, serializer: new BaseSerializer());

        $incrementHashTurbo = hash_init('sha256');
        $incrementHashDefault = hash_init('sha256');

        // across whole range
        $step = \intdiv(PHP_INT_MAX, 1000);
        for ($i = 1; $i < PHP_INT_MAX; $i += $step) {
            \hash_update($incrementHashDefault, $encrypterDefault->encrypt($i));
            \hash_update($incrementHashTurbo, $encrypterTurbo->encrypt($i));
            \hash_update($incrementHashDefault, $encrypterDefault->encrypt(-$i));
            \hash_update($incrementHashTurbo, $encrypterTurbo->encrypt(-$i));
        }

        $this::assertSame(\hash_final($incrementHashDefault), \hash_final($incrementHashTurbo));
    }

    public function testBackwardCompatibility(): void
    {
        $key = 'HtPA2DA8cy2gRUC4h+tKnKIjUt5xuLJzkmKc3MtwZpc=';
        $encrypter = new Sparx128TurboEncrypter(base64secret: $key);
        $this->validateBackwardCompatibility(fn (int $id): string => $encrypter->encrypt($id), PHP_INT_MIN, PHP_INT_MAX, 'Sparx128TurboEncrypter');
    }
}
