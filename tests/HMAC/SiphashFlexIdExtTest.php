<?php

declare(strict_types=1);

namespace Tests\HMAC;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\HMAC\SiphashHMACFlexIdExt;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\HMAC\TestVectors\Siphash24TestVectors;

/**
 * @internal
 */
class SiphashFlexIdExtTest extends TestCase
{
    use Siphash24TestVectors;

    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testWithOfficialVectors(): void
    {
        $key = '';
        for ($i = 0; $i < 16; $i++) {
            $key .= \chr($i);
        }
        $hash = new \FlexId\Siphash($key);

        $input = '';
        $lastInputLen = 0;
        foreach ($this->testVectors as $vector) {
            for ($i = $lastInputLen; $i < $vector['input_len']; $i++) {
                $input .= \chr($i);
            }
            $lastInputLen = $vector['input_len'];
            $this::assertSame($vector['output'], \bin2hex(\pack('Q', $hash->hash($input))));
        }
    }

    public function testAgainstSodium(): void
    {
        $key = \random_bytes(16);
        $hash = new \FlexId\Siphash($key);

        for ($i = 0; $i < 100; $i++) {
            $this::assertSame(
                \bin2hex(\sodium_crypto_shorthash(\pack('Q', $i), $key)),
                \bin2hex(\pack('Q', $hash->hash('', $i))),
            );
        }
    }

    public function testMessageWithNumberExt(): void
    {
        $key = '';
        for ($i = 0; $i < 16; $i++) {
            $key .= \chr($i);
        }
        $hash = new \FlexId\Siphash($key);

        $this::assertSame(
            $hash->hash(\pack('Q', PHP_INT_MAX), null),
            $hash->hash('', PHP_INT_MAX),
        );

        $this::assertSame(
            $hash->hash(\pack('Q', 0), null),
            $hash->hash('', 0),
        );

        for ($i = 1; $i < 100000; $i += 10000) {
            $this::assertSame(
                $hash->hash(\pack('Q', $i) . 'some test', null),
                $hash->hash('some test', $i),
            );
        }
    }

    public function testMessageWithNumber(): void
    {
        $hash = new SiphashHMACFlexIdExt(KeyGen::generate());

        $this::assertSame(
            $hash->sign(\pack('Q', PHP_INT_MAX)),
            $hash->sign('', PHP_INT_MAX),
        );
        $this::assertSame(
            $hash->sign(\pack('Q', 0)),
            $hash->sign('', 0),
        );

        for ($i = 1; $i < 100; $i++) {
            $this::assertSame(
                $hash->sign(\pack('Q', $i) . 'some test'),
                $hash->sign('some test', $i),
            );
        }
    }

    public function testWrongKey(): void
    {
        $key = \random_bytes(15);
        $this::expectException(\InvalidArgumentException::class);
        new SiphashHMACFlexIdExt($key);
    }

    public function testWrongSalt(): void
    {
        $this::expectException(\InvalidArgumentException::class);
        new SiphashHMACFlexIdExt(base64EncodedKey: KeyGen::generate(), hkdfSalt: 'dfhdg');
    }

    public function testWithSalt(): void
    {
        $key = KeyGen::generate();
        $hash = new SiphashHMACFlexIdExt(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());
        $hash2 = new SiphashHMACFlexIdExt(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());

        $this::assertNotSame($hash->sign('foo'), $hash2->sign('foo'));
    }
}
