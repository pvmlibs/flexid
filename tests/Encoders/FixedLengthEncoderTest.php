<?php

declare(strict_types=1);

namespace Tests\Encoders;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encoders\FixedLengthEncoder;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasIdCharDistributionTesting;

/**
 * @internal
 */
final class FixedLengthEncoderTest extends TestCase
{
    use HasEncoderTesting;
    use HasIdCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

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

    public function testEvenCharsDistribution(): void
    {
        $encoder = new FixedLengthEncoder();

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encoder->encode(random_int(0, PHP_INT_MAX));
        }
        $maxDeviations = $this->getMaxDeviation($ids, $encoder->getAlphabet());
        // max deviation 5 times as random one from mean
        $this::assertLessThan($maxDeviations['random'] * 5, $maxDeviations['real']);
    }

    public function testBackwardCompatibility(): void
    {
        $encoder = new FixedLengthEncoder();
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->encode($id), PHP_INT_MAX, 'FixedLengthEncoder');
    }
}
