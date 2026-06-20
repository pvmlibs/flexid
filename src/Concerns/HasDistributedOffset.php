<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Concerns;

/**
 * Helper intended for use in target id class, as it uses static references per id.
 */
trait HasDistributedOffset
{
    /**
     * Distributes each id type through 2^32 space using hash(idUniqueTypeName). Each space has 2^31 range (2147483648 -
     * positive numbers from 32-bits signed integer type). That would make all autoincrement ids nearly unique before
     * encryption, assuming each id has its own id class with defined unique string in idUniqueTypeName().
     * Collision chance for 100 unique strings are about 0.000115 %. This is provided as convenient offsets management
     * with tolerable low risk of hash collisions. If you handle above ~1000 id types (0.0116 % collision chance), you
     * probably should manage offsets manually.
     * This uses only negative range of 64 bit int - positive range can be still used by e.g. timestamp based id.
     */
    protected static function idOffset(): int
    {
        // xxh32 produces 32-bit number, which will be number of the dedicated id offset segment for this id type
        static $offset = null;
        $offset ??= (1 << 31) * (-\hexdec(\hash('xxh32', static::idUniqueTypeName())) - 1);

        return $offset;
    }

    /**
     * Precompute sign data with packed string for offset.
     */
    protected static function getSignData(): string
    {
        static $data = null;
        $data ??= static::idUniqueTypeName() . pack('J', static::idOffset());

        return $data;
    }
}
