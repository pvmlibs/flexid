<?php

namespace Tests\Resolvers;

use PHPUnit\Framework\TestCase;
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
        $this->expectException(\Exception::class);
        $resolver2->resolveWorkerId(1000, 2000);
    }

    public function testResolvingAfterReleaseInSameTimestep(): void
    {
        $workerId = 5;
        $resolver = new StaticWorkerResolver(fn () => $workerId);
        $resolver->clearLock();

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame($workerId, $resolvedWorkerId);
        $resolver->releaseWorker(1000, 1000);

        $resolver = new StaticWorkerResolver(fn () => $workerId, workersBits: 8);

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

    public function testResolveWithOutLock(): void
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
        $this::assertSame($resolver->getConfiguration()->workersBits, $workersBits);
        $this::assertSame($resolver->getConfiguration()->sequenceBits, $sequenceBits);
        $this::assertSame($resolver->getConfiguration()->groupsBits, $groupsBits);
        $this::assertSame($resolver->getConfiguration()->groupId, 0);
    }
}
