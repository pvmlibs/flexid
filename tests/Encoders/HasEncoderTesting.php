<?php

declare(strict_types=1);

namespace Tests\Encoders;

use Pvmlibs\FlexId\Encoders\EncoderContract;

trait HasEncoderTesting
{
    private function validateEncodeDecode(EncoderContract $encoder): void
    {
        $encodedIds = [];
        for ($i = 0; $i < 1000; $i++) {
            $encodedIds[] = $encoder->encode($i);
            $this::assertSame($i, $encoder->decode($encodedIds[$i]));
        }
        $this::assertCount(\count($encodedIds), \array_unique($encodedIds));

        // test linear across whole range
        $encodedIds = [];
        for ($i = 1000; $i < PHP_INT_MAX; $i += \intdiv(PHP_INT_MAX, 10000)) {
            $encodedIds[] = ($id = $encoder->encode($i));
            $this::assertSame($i, $encoder->decode($id));
        }

        $this::assertCount(\count($encodedIds), \array_unique($encodedIds));

        // test random id from whole range
        $encodedIds = [];
        for ($i = 0; $i < 10000; $i++) {
            $id = \random_int(1001, PHP_INT_MAX - 1);
            $encodedIds[] = ($idEncoded = $encoder->encode($id));
            $this::assertSame($id, $encoder->decode($idEncoded));
        }
        $this::assertCount(\count($encodedIds), \array_unique($encodedIds));

        $encoded = $encoder->encode(PHP_INT_MAX);
        $this::assertSame(PHP_INT_MAX, $encoder->decode($encoded));
        $this::assertSame(\strlen($encoded), $encoder->getMaxEncodedLength());
    }
}
