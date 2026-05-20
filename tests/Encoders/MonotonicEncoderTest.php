<?php

declare(strict_types=1);

namespace Tests\Encoders;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encoders\MonotonicEncoder;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

/**
 * @internal
 */
final class MonotonicEncoderTest extends TestCase
{
    public function testEncodeDecode(): void
    {
        $encoder = new MonotonicEncoder();

        $encodedIds = [];
        for ($i = 0; $i < 50; $i++) {
            $encodedIds[] = $encoder->encode($i);
            $this::assertSame($i, $encoder->decode($encodedIds[$i]));
        }
        $this::assertCount(\count($encodedIds), \array_unique($encodedIds));

        $encodedIds = [];
        for ($i = 100; $i < PHP_INT_MAX; $i += \intdiv(PHP_INT_MAX, 10000)) {
            $encodedIds[] = ($id = $encoder->encode($i));
            $this::assertSame($i, $encoder->decode($id));
        }

        $this::assertCount(\count($encodedIds), \array_unique($encodedIds));

        $encoded = $encoder->encode(PHP_INT_MAX);
        $this::assertSame(PHP_INT_MAX, $encoder->decode($encoded));
        $this::assertSame(\strlen($encoded), $encoder->getMaxEncodedLength());
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MonotonicEncoder('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MonotonicEncoder('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MonotonicEncoder('ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MonotonicEncoder('sbâ');
    }

    public function testOutOfRange(): void
    {
        $encoder = new MonotonicEncoder();

        $encoded = $encoder->encode(PHP_INT_MAX);
        $pos = \strpos(MonotonicEncoder::SAFE_ALPHABET, \substr($encoded, 0, 1));
        $newId = MonotonicEncoder::SAFE_ALPHABET[(int) $pos + 1] . \substr($encoded, 1);
        $this->expectException(IdDecodeException::class);
        $encoder->decode($newId);
    }

    public function testDecodeBadEncodedId(): void
    {
        $encoder = new MonotonicEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('');
    }

    public function testDecodeWrongCharEncodedId(): void
    {
        $encoder = new MonotonicEncoder();
        $this->expectException(IdDecodeException::class);
        $encoder->decode('ghry*');
    }

    public function testEncodeBelowRange(): void
    {
        $encoder = new MonotonicEncoder();
        $this->expectException(IdEncodeException::class);
        $encoder->encode(-1);
    }
}
