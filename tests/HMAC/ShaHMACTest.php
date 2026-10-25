<?php

declare(strict_types=1);

namespace HMAC;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\HMAC\Sha256HMAC;
use Pvmlibs\FlexId\Tools\KeyGen;

/**
 * @internal
 */
class ShaHMACTest extends TestCase
{
    public function testOutputs(): void
    {
        $key = \base64_encode(KeyGen::generate());

        $hmac = new Sha256HMAC($key, 'info 1');
        $hmac2 = new Sha256HMAC($key, 'info 2');

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
        new Sha256HMAC($key);
    }
}
