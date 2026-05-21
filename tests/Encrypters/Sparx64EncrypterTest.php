<?php

declare(strict_types=1);

namespace Tests\Encrypters;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Encrypters\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * @internal
 */
final class Sparx64EncrypterTest extends TestCase
{
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

    private function runBatch(Sparx64Encrypter $encrypter): void
    {
        $encryptedIds = [];
        for ($i = 0; $i < 50; $i++) {
            $encryptedIds[] = $encrypter->encrypt($i);
            $this::assertSame($i, $encrypter->decrypt($encryptedIds[$i]));
        }
        $this::assertCount(\count($encryptedIds), \array_unique($encryptedIds));

        // test linear across whole range
        $step = \intdiv(PHP_INT_MAX, 1000);
        $encryptedIds = [];

        for ($i = 100; $i < PHP_INT_MAX; $i += $step) {
            $encryptedIds[] = ($id = $encrypter->encrypt($i));
            $this::assertSame($i, $encrypter->decrypt($id));
        }

        $this::assertCount(\count($encryptedIds), \array_unique($encryptedIds));

        // test random id from whole range
        $encryptedIds = [];
        for ($i = 0; $i < 10000; $i++) {
            $id = \random_int(1001, PHP_INT_MAX - 1);
            $encryptedIds[] = ($idEncrypted = $encrypter->encrypt($id));
            $this::assertSame($id, $encrypter->decrypt($idEncrypted));
        }

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
}
