<?php

declare(strict_types=1);

namespace HMAC;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\HMAC\SiphashHMACSodiumExt;
use Pvmlibs\FlexId\Tools\KeyGen;

/**
 * @internal
 */
class SiphashSodiumTest extends TestCase
{
    public function testOutputs(): void
    {
        $key = \base64_encode(KeyGen::generate());

        $hmac = new SiphashHMACSodiumExt($key, 'info 1');
        $hmac2 = new SiphashHMACSodiumExt($key, 'info 2');

        $this::assertNotSame($hmac->sign('foo'), $hmac2->sign('foo'));
        $this::assertCount(1, $hmac->sign('foo'));

        $this::expectException(\InvalidArgumentException::class);
        $hmac->setup(0);
    }

    public function testWrongKey(): void
    {
        $key = \random_bytes(15);
        $this::expectException(\InvalidArgumentException::class);
        new SiphashHMACSodiumExt($key);
    }

    public function testWrongSalt(): void
    {
        $this::expectException(\InvalidArgumentException::class);
        new SiphashHMACSodiumExt(base64EncodedKey: KeyGen::generate(), hkdfSalt: 'dfhdg');
    }

    public function testWithSalt(): void
    {
        $key = KeyGen::generate();
        $hash = new SiphashHMACSodiumExt(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());
        $hash2 = new SiphashHMACSodiumExt(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());

        $this::assertNotSame($hash->sign('foo'), $hash2->sign('foo'));
    }
}
