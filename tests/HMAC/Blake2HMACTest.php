<?php

declare(strict_types=1);

namespace HMAC;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\HMAC\Blake2HMACSodiumExt;
use Pvmlibs\FlexId\Tools\KeyGen;

/**
 * @internal
 */
class Blake2HMACTest extends TestCase
{
    public function testOutputs(): void
    {
        $key = \base64_encode(KeyGen::generate());

        $hmac = new Blake2HMACSodiumExt($key, 'info 1');
        $hmac2 = new Blake2HMACSodiumExt($key, 'info 2');

        $this::assertNotSame($hmac->sign('foo'), $hmac2->sign('foo'));
        $this::assertCount(1, $hmac->sign('foo'));

        for ($i = 2; $i <= 4; $i++) {
            $hmac->setup($i);
            $this::assertCount($i, $hmac->sign('foo'));
        }

        $this::expectException(\InvalidArgumentException::class);
        $hmac->setup(0);
    }

    public function testWrongKey(): void
    {
        $key = \base64_encode(\random_bytes(16));
        $this::expectException(\InvalidArgumentException::class);
        new Blake2HMACSodiumExt($key);
    }

    public function testWrongSalt(): void
    {
        $this::expectException(\InvalidArgumentException::class);
        new Blake2HMACSodiumExt(base64EncodedKey: KeyGen::generate(), hkdfSalt: 'dfhdg');
    }

    public function testWithSalt(): void
    {
        $key = KeyGen::generate();
        $hash = new Blake2HMACSodiumExt(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());
        $hash2 = new Blake2HMACSodiumExt(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());

        $this::assertNotSame($hash->sign('foo'), $hash2->sign('foo'));
    }
}
