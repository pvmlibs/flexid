<?php

declare(strict_types=1);

namespace Tests\Encrypters\Ciphers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx64Cipher;
use Pvmlibs\FlexId\Tools\KeyGen;

/**
 * @internal
 */
final class Sparx64CipherTest extends TestCase
{
    use HasCipher64Testing;

    public function testWithVectors(): void
    {
        $key = \pack('v8', 0x0011, 0x2233, 0x4455, 0x6677, 0x8899, 0xAABB, 0xCCDD, 0xEEFF);
        $plaintext = \pack('v4', 0x0123, 0x4567, 0x89AB, 0xCDEF);
        $ciphertext = \pack('v4', 0x2BBE, 0xF152, 0x01F5, 0x5F98);

        $cipher = new Sparx64Cipher(\base64_encode($key));
        $unpacked = \unpack('Q', $plaintext);
        $this::assertNotFalse($unpacked);

        $this::assertSame($ciphertext, \pack('Q', $cipher->encrypt($unpacked[1])));
    }

    public function testBatch(): void
    {
        $cipher = new Sparx64Cipher(KeyGen::generate());

        $this->runBatch($cipher);
    }
}
