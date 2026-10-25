<?php

declare(strict_types=1);

namespace Encrypters\Ciphers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Ciphers\AesCipher;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\Encrypters\Ciphers\HasCipher128Testing;

/**
 * @internal
 */
final class AesCipherTest extends TestCase
{
    use HasCipher128Testing;

    public function testWithVectors(): void
    {
        $plaintext = \pack('Q2', 0, 0);
        $bin = \hex2bin('46f2fb342d6f0ab477476fc501242c5f');
        $this::assertNotFalse($bin);
        $ciphertext = \unpack('Q2', $bin);
        $this::assertNotFalse($ciphertext);

        $bin = \hex2bin('c47b0294dbbbee0fec4757f22ffeee3587ca4730c3d33b691df38bab076bc558');
        $this::assertNotFalse($bin);

        $cipher = new AesCipher(\base64_encode($bin));

        $unpacked = \unpack('Q2', $plaintext);
        $this::assertNotFalse($unpacked);
        $encrypted = $cipher->encrypt($unpacked[1], $unpacked[2]);

        $this::assertSame($ciphertext[1], $encrypted[0]);
        $this::assertSame($ciphertext[2], $encrypted[1]);
    }

    public function testBatch(): void
    {
        $cipher = new AesCipher(KeyGen::generate());

        $this->runBatch($cipher);
    }
}
