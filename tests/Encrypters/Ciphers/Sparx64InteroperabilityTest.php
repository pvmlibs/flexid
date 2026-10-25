<?php

declare(strict_types=1);

namespace Encrypters\Ciphers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx64Cipher;
use Pvmlibs\FlexId\Encrypters\Ciphers\Sparx64CipherFlexIdExt;
use Pvmlibs\FlexId\Tools\KeyGen;
use Tests\Encrypters\Ciphers\HasCipher64Testing;

/**
 * @internal
 */
final class Sparx64InteroperabilityTest extends TestCase
{
    use HasCipher64Testing;

    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testImplementations(): void
    {
        $key = KeyGen::generate();
        $cipherPHP = new Sparx64Cipher($key);
        $cipherFlexId = new Sparx64CipherFlexIdExt($key);

        $this::assertSame($this->runDeterministicBatch($cipherPHP), $this->runDeterministicBatch($cipherFlexId));
    }
}
