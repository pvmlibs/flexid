<?php

namespace Tests\Resolvers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\Resolvers\RandomWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\Resolvers\RandomWorkerResolver
 *
 * @internal
 */
final class RandomResolverTest extends TestCase
{
    public function testWorkerResolving(): void
    {
        $resolver = new RandomWorkerResolver();
        $maxWorkers = $resolver->getConfiguration()->maxWorkers;
        $workers = [];

        for ($i = 0; $i < $maxWorkers; $i++) {
            $workers[] = $workerId = $resolver->resolveWorkerId(1000, 1000);
            $this::assertTrue($workerId >= 0 && $workerId < $maxWorkers);
        }

        $this::assertCount($maxWorkers, \array_unique($workers));
        $this::assertSame($workers[$maxWorkers - 1], $resolver->getCurrentWorkerId());

        // in this timestep there can be no more workers
        $this->expectException(NoWorkerAvailableException::class);
        $resolver->resolveWorkerId(1000, 1000);
    }

    public function testWorkersOverflowResolving(): void
    {
        $resolver = new RandomWorkerResolver();
        $maxWorkers = $resolver->getConfiguration()->maxWorkers;

        $workersInTimesteps = [];
        $currentTimeStep = \hrtime(true) & (-1 << $resolver->getConfiguration()->totalMetaDataBits);

        for ($i = 0; $i < $maxWorkers; $i++) {
            $worker = $resolver->resolveWorkerId(0, $currentTimeStep);
            $workersInTimesteps[] = $currentTimeStep | $worker;
        }
        $this::assertCount($maxWorkers, \array_unique($workersInTimesteps));

        $this->expectException(NoWorkerAvailableException::class);
        $resolver->resolveWorkerId(0, $currentTimeStep);
    }

    public function testResolverWithPidInvalid(): void
    {
        $this->expectException(\DomainException::class);
        new RandomWorkerResolver(workersBits: 11, pidBits: 12);
    }

    public function testResolverWithPid(): void
    {
        $resolver = new RandomWorkerResolver(workersBits: 11, pidBits: 5);
        $maxWorkersWithinPid = 1 << (11 - 5);
        $pid = (\getmypid() | 0) % (1 << 5);
        $workers = [];

        for ($i = 0; $i < $maxWorkersWithinPid; $i++) {
            $workers[] = $workerId = $resolver->resolveWorkerId(1000, 1000);
            $this::assertTrue($workerId >= 0 && $workerId < $resolver->getConfiguration()->maxWorkers);
        }

        $this::assertCount($maxWorkersWithinPid, \array_unique($workers));
        $this::assertSame($workers[$maxWorkersWithinPid - 1], $resolver->getCurrentWorkerId());
        $pidFromWorker = $workers[$maxWorkersWithinPid - 1] >> (11 - 5);
        $this::assertSame($pid, $pidFromWorker);
    }

    public function testReleaseWorker(): void
    {
        $resolver = new RandomWorkerResolver();
        $resolver->resolveWorkerId(1000, 1000);
        $this::assertTrue($resolver->releaseWorker());
        $this::assertSame(-1, $resolver->getCurrentWorkerId());
        $this::assertFalse($resolver->releaseWorker());
    }

    public function testResolverConfig(): void
    {
        $workersBits = 16;
        $groupsBits = 1;
        $resolver = new RandomWorkerResolver(workersBits: $workersBits, groupsBits: $groupsBits);
        $this::assertTrue($resolver->dependsOnTimestamp());
        $this::assertSame(2, $resolver->getMaxWorkerResolveTrials());
        $this::assertSame($resolver->getConfiguration()->workersBits, $workersBits);
        $this::assertSame($resolver->getConfiguration()->sequenceBits, 0);
        $this::assertSame($resolver->getConfiguration()->groupsBits, $groupsBits);
        $this::assertSame($resolver->getConfiguration()->groupId, 0);
    }
}
