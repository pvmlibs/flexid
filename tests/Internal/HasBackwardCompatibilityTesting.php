<?php

declare(strict_types=1);

namespace Tests\Internal;

trait HasBackwardCompatibilityTesting
{
    /**
     * @param \Closure(int $id): string $feed
     */
    private function validateBackwardCompatibility(\Closure $feed, int $maxRange, string $name): void
    {
        $incrementHash = hash_init('sha256');

        // sequential
        for ($i = 0; $i < 1000; $i++) {
            \hash_update($incrementHash, $feed($i));
        }

        // across whole range
        $step = \intdiv($maxRange, 1000);
        for ($i = 1000; $i < $maxRange; $i += $step) {
            \hash_update($incrementHash, $feed($i));
        }

        // max
        \hash_update($incrementHash, $feed($maxRange));
        $hash = \hash_final($incrementHash);

        $this::assertSame($hash, file_get_contents(__DIR__ . "/BackwardCompatibility/{$name}.txt"));
    }
}
