<?php

declare(strict_types=1);

namespace Tests\Encoders;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encoders\RotatedAlphabetEncoder;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * @internal
 */
final class RotatedAlphabetEncoderTest extends TestCase
{
    use HasEncoderTesting;

    public function testWithDefaultAlphabet(): void
    {
        $encoder = new RotatedAlphabetEncoder();
        $this->validateEncodeDecode($encoder);
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RotatedAlphabetEncoder('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RotatedAlphabetEncoder('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RotatedAlphabetEncoder('ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RotatedAlphabetEncoder('sbâ');
    }

    public function testDecodeOutOfRange(): void
    {
        $encoder = new RotatedAlphabetEncoder();
        $encoded = $encoder->encode(PHP_INT_MAX);
        $this->expectException(IdDecodeException::class);
        $encoder->decode(\substr($encoded, 0, -1) . \substr(RotatedAlphabetEncoder::SAFE_ALPHABET, -1));
    }

    public function testDecodeBadEncodedId(): void
    {
        $encoder = new RotatedAlphabetEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('');
    }

    public function testDecodeWrongCharEncodedId(): void
    {
        $encoder = new RotatedAlphabetEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('ghry*');
    }

    public function testDecodeWrongFirstCharEncodedId(): void
    {
        $encoder = new RotatedAlphabetEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('*ghry');
    }

    public function testEncodeBelowRange(): void
    {
        $encoder = new RotatedAlphabetEncoder();
        $this->expectException(IdEncodeException::class);
        $encoder->encode(-1);
    }

    public function testIsConstantLength(): void
    {
        $encoder = new RotatedAlphabetEncoder();
        $this::assertTrue($encoder->isConstantLength());
    }

    public function testNoSequentialRepetition(): void
    {
        $total = 1000;
        $ids = new \SplFixedArray($total);
        $encoder = new RotatedAlphabetEncoder();

        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encoder->encode($i + 1000000);
        }

        $similar = 0;
        // check every pair for the last same 3 chars
        for ($i = 0; $i < $total - 1; $i++) {
            $ref = \substr($ids[$i], -3);
            for ($j = $i + 1; $j < $total; $j++) {
                if ($ref === \substr($ids[$j], -3)) {
                    // count similar, much faster than asserting each pair
                    $similar++;
                }
            }
        }
        $this::assertEquals(0, $similar);
    }
}
