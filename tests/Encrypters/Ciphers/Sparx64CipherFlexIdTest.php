<?php

declare(strict_types=1);

namespace Encrypters\Ciphers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx64CipherFlexIdExt;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\Encrypters\Ciphers\HasCipher64Testing;

/**
 * @internal
 */
final class Sparx64CipherFlexIdTest extends TestCase
{
    use HasCipher64Testing;

    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testWithVectors(): void
    {
        $key = \pack('v8', 0x0011, 0x2233, 0x4455, 0x6677, 0x8899, 0xAABB, 0xCCDD, 0xEEFF);
        $plaintext = \pack('v4', 0x0123, 0x4567, 0x89AB, 0xCDEF);
        $ciphertext = \pack('v4', 0x2BBE, 0xF152, 0x01F5, 0x5F98);

        $cipher = new Sparx64CipherFlexIdExt(\base64_encode($key));

        $unpacked = \unpack('Q', $plaintext);
        $this::assertNotFalse($unpacked);

        $this::assertSame($ciphertext, \pack('Q', $cipher->encrypt($unpacked[1])));
    }

    public function testBatch(): void
    {
        $cipher = new Sparx64CipherFlexIdExt(KeyGen::generate());

        $this->runBatch($cipher);
    }
}
