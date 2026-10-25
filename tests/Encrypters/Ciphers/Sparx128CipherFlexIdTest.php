<?php

declare(strict_types=1);

namespace Encrypters\Ciphers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx128CipherFlexIdExt;
use Pvmlibs\FlexId\Tools\KeyGen;

/**
 * @internal
 */
final class Sparx128CipherFlexIdTest extends TestCase
{
    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testWithVectors(): void
    {
        $key = \pack(
            'v16',
            0x0011,
            0x2233,
            0x4455,
            0x6677,
            0x8899,
            0xAABB,
            0xCCDD,
            0xEEFF,
            0xFFEE,
            0xDDCC,
            0xBBAA,
            0x9988,
            0x7766,
            0x5544,
            0x3322,
            0x1100,
        );
        $plaintext = \pack('v8', 0x0123, 0x4567, 0x89AB, 0xCDEF, 0xFEDC, 0xBA98, 0x7654, 0x3210);
        $ciphertext = \pack('v8', 0x3328, 0xE637, 0x14C7, 0x6CE6, 0x32D1, 0x5A54, 0xE4B0, 0xC820);

        $cipher = new Sparx128CipherFlexIdExt(\base64_encode($key));

        $unpacked = \unpack('Q2', $plaintext);
        $this::assertNotFalse($unpacked);
        $encrypted = $cipher->encrypt($unpacked[1], $unpacked[2]);
        $this::assertSame($ciphertext, \pack('Q2', $encrypted[0], $encrypted[1]));
    }

    public function testRound(): void
    {
        $cipher = new Sparx128CipherFlexIdExt(KeyGen::generate());

        $num1 = PHP_INT_MAX;
        $num2 = PHP_INT_MIN;
        $encrypted = $cipher->encrypt($num1, $num2);
        $decrypted = $cipher->decrypt($encrypted[0], $encrypted[1]);
        $this::assertNotSame($num1, $encrypted[0]);
        $this::assertNotSame($num2, $encrypted[1]);
        $this::assertSame($num1, $decrypted[0]);
        $this::assertSame($num2, $decrypted[1]);
    }
}
