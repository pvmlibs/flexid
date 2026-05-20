<?php

declare(strict_types=1);

namespace Tests\Resolvers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\Resolvers\ApcuTimestepWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\Resolvers\StaticWorkerResolver
 *
 * @internal
 */
final class AcpuResolverTest extends TestCase
{
    public function testWorkerResolving(): void
    {
        \apcu_clear_cache();
        $workerId = 0;
        $resolver = new ApcuTimestepWorkerResolver();
        $resolver->clearLock();

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame($workerId, $resolvedWorkerId);

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        // same timestep
        $this::assertSame($workerId + 1, $resolvedWorkerId);

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000 + $resolver->getConfiguration()->timestepNs);
        // next timestep, r
        $this::assertSame($workerId, $resolvedWorkerId);

        $resolver->releaseWorker(1000, 1000);

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame($workerId + 2, $resolvedWorkerId);
        $this::assertSame($resolvedWorkerId, $resolver->getCurrentWorkerId());
    }

    public function testResolvingAfterDbClear(): void
    {
        \apcu_clear_cache();
        $resolver = new ApcuTimestepWorkerResolver(timestampBitshift: 1);
        $resolver->clearLock();

        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame(0, $resolvedWorkerId);
        $resolver->releaseWorker(1000, 1000);
        // simulate process exit
        unset($resolver);
        $resolver = new ApcuTimestepWorkerResolver(timestampBitshift: 1);
        \apcu_clear_cache();

        // should resolve worker when in next timestep
        $resolvedWorkerId = $resolver->resolveWorkerId(1000, 1000 + $resolver->getConfiguration()->timestepNs);
        $this::assertSame(0, $resolvedWorkerId);

        // but will throw exception if within same timestep - generator can retry after wait
        $this->expectException(NoWorkerAvailableException::class);
        $resolver->resolveWorkerId(1000, 1000);
    }

    public function testDefaultWorker(): void
    {
        \apcu_clear_cache();
        $resolver = new ApcuTimestepWorkerResolver();
        $resolver->clearLock();
        $this::assertSame(-1, $resolver->getCurrentWorkerId());
    }

    public function testReleaseWorker(): void
    {
        \apcu_clear_cache();
        $resolver = new ApcuTimestepWorkerResolver();
        $resolver->clearLock();
        $resolver->resolveWorkerId(1000, 1000);
        $this::assertTrue($resolver->releaseWorker());
    }

    public function testResolveWithoutLockFile(): void
    {
        $resolver = new ApcuTimestepWorkerResolver(workerLockFilePath: null);
        $resolver->clearLock();
        \apcu_clear_cache();
        $resolver->resolveWorkerId(1000, 1000);
        $this::assertSame(0, $resolver->getCurrentWorkerId());
    }

    public function testResolverConfig(): void
    {
        $workersBits = 12;
        $sequenceBits = 12;
        $groupsBits = 1;
        $resolver = new ApcuTimestepWorkerResolver(workersBits: $workersBits, sequenceBits: $sequenceBits, groupsBits: $groupsBits);
        $resolver->clearLock();
        $this::assertTrue($resolver->dependsOnTimestamp());
        $this::assertSame(2, $resolver->getMaxWorkerResolveTrials());
        $this::assertSame($workersBits, $resolver->getConfiguration()->workersBits);
        $this::assertSame($sequenceBits, $resolver->getConfiguration()->sequenceBits);
        $this::assertSame($groupsBits, $resolver->getConfiguration()->groupsBits);
        $this::assertSame(0, $resolver->getConfiguration()->groupId);
    }
}
