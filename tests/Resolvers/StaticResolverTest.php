<?php

declare(strict_types=1);

namespace Tests\Resolvers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdConfigurationException;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\Resolvers\StaticWorkerResolver
 *
 * @internal
 */
final class StaticResolverTest extends TestCase
{
    public function testWorkerResolving(): void
    {
        $workerId = 5;
        $resolver = new StaticWorkerResolver(fn () => $workerId, workersBits: 8);
        $resolver->clearLock();

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame($workerId, $resolvedWorkerId);

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame($workerId, $resolvedWorkerId);

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 2000);
        $this::assertSame($workerId, $resolvedWorkerId);
        $this::assertSame($workerId, $resolver->getCurrentWorkerId());
    }

    public function testAnotherInstanceWorkerResolving(): void
    {
        $workerId = 5;
        $resolver = new StaticWorkerResolver(fn () => $workerId);
        $resolver->clearLock();

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame($workerId, $resolvedWorkerId);
        $resolver->releaseWorker(1000, 1000);

        $resolver2 = new StaticWorkerResolver(fn () => $workerId, workersBits: 8);
        $resolver->clearLock();
        $this->expectException(IdConfigurationException::class);
        $resolver2->resolveWorkerId(1000, 2000);
    }

    public function testResolvingAfterReleaseInSameTimestep(): void
    {
        $workerId = 5;
        $resolver = new StaticWorkerResolver(fn () => $workerId);
        $resolver->clearLock();
        $resolver2 = new StaticWorkerResolver(fn () => $workerId, workersBits: 14, timestampBitshift: 16);
        $resolver2->clearLock();

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame($workerId, $resolvedWorkerId);
        $resolver->releaseWorker(1000, 1000);

        $resolver = new StaticWorkerResolver(fn () => $workerId, workersBits: 8);

        // different time configuration should have own config so this should pass
        $resolvedWorkerId2 = $resolver2->resolveWorkerId(1000, 1000);
        $this::assertSame($resolvedWorkerId, $resolvedWorkerId2);

        $this->expectException(NoWorkerAvailableException::class);
        $resolver->resolveWorkerId(1000, 1000);
    }

    public function testReleaseWorker(): void
    {
        $resolver = new StaticWorkerResolver(fn () => 1);
        $resolver->clearLock();
        $resolver->resolveWorkerId(1000, 1000);
        $this::assertTrue($resolver->releaseWorker());
    }

    public function testResolveWithoutLockFile(): void
    {
        $resolver = new StaticWorkerResolver(workerHandlerFn: fn () => 1, workerLockFilePath: null);
        $resolver->clearLock();
        $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame(1, $resolver->getCurrentWorkerId());
    }

    public function testResolverConfig(): void
    {
        $workersBits = 16;
        $sequenceBits = 8;
        $groupsBits = 1;
        $resolver = new StaticWorkerResolver(fn () => 1, workersBits: $workersBits, sequenceBits: $sequenceBits, groupsBits: $groupsBits);
        $this::assertFalse($resolver->dependsOnTimestamp());
        $this::assertSame(2, $resolver->getMaxWorkerResolveTrials());
        $this::assertSame($workersBits, $resolver->getConfiguration()->workersBits);
        $this::assertSame($sequenceBits, $resolver->getConfiguration()->sequenceBits);
        $this::assertSame($groupsBits, $resolver->getConfiguration()->groupsBits);
        $this::assertSame(0, $resolver->getConfiguration()->groupId);
    }
}
