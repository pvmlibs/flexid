<?php

declare(strict_types=1);

namespace Encrypters\Ciphers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx128Cipher;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx128CipherFlexIdExt;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\Encrypters\Ciphers\HasCipher128Testing;

/**
 * @internal
 */
final class Sparx128InteroperabilityTest extends TestCase
{
    use HasCipher128Testing;

    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testImplementations(): void
    {
        $key = KeyGen::generate();
        $cipherPHP = new Sparx128Cipher($key);
        $cipherFlexId = new Sparx128CipherFlexIdExt($key);

        $this::assertSame($this->runDeterministicBatch($cipherPHP), $this->runDeterministicBatch($cipherFlexId));
    }
}
