<?php

declare(strict_types=1);

namespace HMAC;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\HMAC\SiphashHMAC;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\HMAC\TestVectors\Siphash24TestVectors;

/**
 * @internal
 */
class SiphashHMACTest extends TestCase
{
    use Siphash24TestVectors;

    public function testSiphashHMAC(): void
    {
        $key = \base64_encode(KeyGen::generate());

        $hmac = new SiphashHMAC($key, 'info 1');
        $hmac2 = new SiphashHMAC($key, 'info 2');

        $this::assertNotSame($hmac->sign('foo'), $hmac2->sign('foo'));
        $this::assertCount(1, $hmac->sign('foo'));

        $this::expectException(\InvalidArgumentException::class);
        $hmac->setup(2);
    }

    public function testWrongKey(): void
    {
        $key = \base64_encode(random_bytes(15));
        $this::expectException(\InvalidArgumentException::class);
        new SiphashHMAC($key);
    }

    public function testWrongSalt(): void
    {
        $this::expectException(\InvalidArgumentException::class);
        new SiphashHMAC(base64EncodedKey: KeyGen::generate(), hkdfSalt: 'dfhdg');
    }

    public function testWithSalt(): void
    {
        $key = KeyGen::generate();
        $hash = new SiphashHMAC(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());
        $hash2 = new SiphashHMAC(base64EncodedKey: $key, hkdfSalt: KeyGen::generate());

        $this::assertNotSame($hash->sign('foo'), $hash2->sign('foo'));
    }
}
