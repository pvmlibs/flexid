<?php

declare(strict_types=1);

namespace Encoders;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encoders\FixedLengthEncoder;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Tests\Encoders\HasEncoderTesting;

/**
 * @internal
 */
final class FixedLengthEncoderTest extends TestCase
{
    use HasEncoderTesting;

    public function testWithDefaultAlphabet(): void
    {
        $encoder = new FixedLengthEncoder();
        $this->validateEncodeDecode($encoder);
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedLengthEncoder('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedLengthEncoder('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedLengthEncoder('QFCgDRwkBxMLGKdd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedLengthEncoder('QFCgDRwkBxMLGKdâ');
    }

    public function testOutOfRange(): void
    {
        $encoder = new FixedLengthEncoder();

        $encoded = $encoder->encode(PHP_INT_MAX);
        $pos = \strpos(FixedLengthEncoder::ALPHABET, \substr($encoded, 0, 1));
        $newId = FixedLengthEncoder::ALPHABET[(int) $pos + 1] . \substr($encoded, 1);
        $this->expectException(IdDecodeException::class);
        $encoder->decode($newId);
    }

    public function testDecodeBadEncodedId(): void
    {
        $encoder = new FixedLengthEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('');
    }

    public function testDecodeWrongCharEncodedId(): void
    {
        $encoder = new FixedLengthEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('ghry*');
    }

    public function testEncodeBelowRange(): void
    {
        $encoder = new FixedLengthEncoder();
        $this->expectException(IdEncodeException::class);
        $encoder->encode(-1);
    }

    public function testGetAlphabet(): void
    {
        $encoder = new FixedLengthEncoder('0123456789abcdef');
        $this::assertSame('0123456789abcdef', $encoder->getAlphabet());
    }

    public function testIsConstantLength(): void
    {
        $encoder = new FixedLengthEncoder();
        $this::assertTrue($encoder->isConstantLength());
    }
}
