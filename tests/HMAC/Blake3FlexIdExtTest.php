<?php

declare(strict_types=1);

namespace Tests\HMAC;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\HMAC\Blake3HMACFlexIdExt;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\HMAC\TestVectors\Blake3TestVectors;

/**
 * @internal
 */
class Blake3FlexIdExtTest extends TestCase
{
    use Blake3TestVectors;

    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testWithOfficialVectors(): void
    {
        // https://github.com/BLAKE3-team/BLAKE3/blob/master/test_vectors/test_vectors.json
        $blake3 = new \FlexId\Blake3('whats the Elvish word for friend');

        $input = '';
        $lastInputLen = 0;
        foreach ($this->testVectors as $vector) {
            /** @var non-negative-int $i */
            for ($i = $lastInputLen; $i < $vector['input_len']; $i++) {
                $input .= \chr($i % 251);
            }
            $lastInputLen = $vector['input_len'];
            $this::assertSame($vector['output'], $this->arrtohex($blake3->hash($input, null, 4)));
        }
    }

    /**
     * @param list<int> $a
     */
    private function arrtohex(array $a): string
    {
        $result = '';
        foreach ($a as $v) {
            $result .= \bin2hex(\pack('Q', $v));
        }

        return $result;
    }

    public function testMessageWithNumberExt(): void
    {
        $hash = new \FlexId\Blake3('whats the Elvish word for friend');

        $this::assertSame(
            $hash->hash(\pack('Q', PHP_INT_MAX), null, 4),
            $hash->hash('', PHP_INT_MAX, 4),
        );

        $this::assertSame(
            $hash->hash(\pack('Q', 0), null, 4),
            $hash->hash('', 0, 4),
        );

        for ($i = 1; $i < 100000; $i += 10000) {
            $this::assertSame(
                $hash->hash(\pack('Q', $i) . 'some test', null, 4),
                $hash->hash('some test', $i, 4),
            );
        }
    }

    public function testMessageWithNumber(): void
    {
        $hash = new Blake3HMACFlexIdExt(KeyGen::generate());

        $this::assertSame(
            $hash->sign(\pack('Q', PHP_INT_MAX)),
            $hash->sign('', PHP_INT_MAX),
        );
        $this::assertSame(
            $hash->sign(\pack('Q', 0)),
            $hash->sign('', 0),
        );

        for ($i = 1; $i < 100000; $i += 200) {
            $this::assertSame(
                $hash->sign(\pack('Q', $i) . 'some test'),
                $hash->sign('some test', $i),
            );
        }
    }

    public function testTooLongMessage(): void
    {
        $hash = new Blake3HMACFlexIdExt(KeyGen::generate());
        $this::expectException(\InvalidArgumentException::class);
        $hash->sign(\str_repeat('a', 1025));
    }

    public function testOutputLength(): void
    {
        $hash = new Blake3HMACFlexIdExt(KeyGen::generate());

        for ($i = 1; $i <= 4; $i++) {
            $hash->setup($i);
            $result = $hash->sign('foo');
            $this::assertCount($i, $result);
        }
        $this::expectException(\InvalidArgumentException::class);
        $hash->setup(0);
    }

    public function testWrongKey(): void
    {
        $key = \random_bytes(15);
        $this::expectException(\InvalidArgumentException::class);
        new Blake3HMACFlexIdExt($key);
    }

    public function testWrongSalt(): void
    {
        $this::expectException(\InvalidArgumentException::class);
        new Blake3HMACFlexIdExt(base64EncodedKey: KeyGen::generate(), hkdfSalt: 'dfhdg');
    }

    public function testWithSalt(): void
    {
        $key = KeyGen::generate();
        $hash = new Blake3HMACFlexIdExt(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());
        $hash2 = new Blake3HMACFlexIdExt(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());

        $this::assertNotSame($hash->sign('foo'), $hash2->sign('foo'));
    }
}
