<?php

declare(strict_types=1);

namespace Encoders;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encoders\HexEncoder;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Tests\Encoders\HasEncoderTesting;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasIdCharDistributionTesting;

/**
 * @internal
 */
final class HexEncoderTest extends TestCase
{
    use HasEncoderTesting;
    use HasIdCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testWithDefaultAlphabet(): void
    {
        $encoder = new HexEncoder();
        $this->validateEncodeDecode($encoder);
    }

    public function testOutOfRange(): void
    {
        $encoder = new HexEncoder();

        $encoded = $encoder->encode(PHP_INT_MAX);
        $this->expectException(IdDecodeException::class);
        $encoder->decode($encoded . 'a');
    }

    public function testDecodeBadEncodedId(): void
    {
        $encoder = new HexEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('');
    }

    public function testDecodeWrongCharEncodedId(): void
    {
        $encoder = new HexEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('aghry*');
    }

    public function testEncodeBelowRange(): void
    {
        $encoder = new HexEncoder();
        $this->expectException(IdEncodeException::class);
        $encoder->encode(-1);
    }

    public function testGetAlphabet(): void
    {
        $encoder = new HexEncoder();
        $this::assertSame('0123456789abcdef', $encoder->getAlphabet());
    }

    public function testIsConstantLength(): void
    {
        $encoder = new HexEncoder();
        $this::assertFalse($encoder->isConstantLength());
    }

    public function testEvenCharsDistribution(): void
    {
        $encoder = new HexEncoder();

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
        $encoder = new HexEncoder();
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->encode($id), PHP_INT_MAX, 'HexEncoder');
    }
}
