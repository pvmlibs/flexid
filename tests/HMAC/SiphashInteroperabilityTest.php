<?php

declare(strict_types=1);

namespace Tests\HMAC;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\HMAC\SiphashHMACFlexIdExt;
use Pvmlibs\FlexId\HMAC\SiphashHMACSodiumExt;
use Tests\HMAC\TestVectors\Siphash24TestVectors;

/**
 * @internal
 */
class SiphashInteroperabilityTest extends TestCase
{
    use Siphash24TestVectors;

    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testInteroperability(): void
    {
        $key = \base64_encode(random_bytes(16));

        $sodiumHash = new SiphashHMACSodiumExt($key, 'same hkdf info');
        $flexIdHash = new SiphashHMACFlexIdExt($key, 'same hkdf info');

        for ($i = 1; $i < 100; $i++) {
            $input = random_bytes($i);
            $this::assertSame($sodiumHash->sign($input, $i), $flexIdHash->sign($input, $i));
        }
    }
}
