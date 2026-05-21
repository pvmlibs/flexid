<?php

declare(strict_types=1);

namespace Tests\Encoders;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encoders\PseudoRandomEncoder;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * @internal
 */
final class PseudoRandomEncoderTest extends TestCase
{
    use HasEncoderTesting;

    public function testWithDefaultAlphabet(): void
    {
        $encoder = new PseudoRandomEncoder();
        $this->validateEncodeDecode($encoder);
    }

    public function testWithExtendedAlphabet(): void
    {
        $encoder = new PseudoRandomEncoder(PseudoRandomEncoder::EXTENDED_ALPHABET);
        $this->validateEncodeDecode($encoder);
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PseudoRandomEncoder('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PseudoRandomEncoder('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PseudoRandomEncoder('ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PseudoRandomEncoder('sbâ');
    }

    public function testDecodeOutOfRange(): void
    {
        $encoder = new PseudoRandomEncoder();
        $encoded = $encoder->encode(PHP_INT_MAX);
        $this->expectException(IdDecodeException::class);
        $encoder->decode(\substr($encoded, 0, -1) . \substr(PseudoRandomEncoder::SAFE_ALPHABET, -1));
    }

    public function testDecodeBadEncodedId(): void
    {
        $encoder = new PseudoRandomEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('');
    }

    public function testDecodeWrongCharEncodedId(): void
    {
        $encoder = new PseudoRandomEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('ghry*');
    }

    public function testEncodeBelowRange(): void
    {
        $encoder = new PseudoRandomEncoder();
        $this->expectException(IdEncodeException::class);
        $encoder->encode(-1);
    }
}
