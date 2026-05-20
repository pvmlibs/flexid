<?php

declare(strict_types=1);

namespace Tests\Resolvers;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver;
use Tests\Internal\hasRedisClient;

/**
 * @covers \Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver
 *
 * @internal
 */
final class RedisTimestepWorkerResolverTest extends TestCase
{
    use hasRedisClient;

    public function testWorkerResolver(): void
    {
        $this->workerResolve($this->getRedisClient());
        $this->workerResolve($this->getPRedisClient());
    }

    public function testReleaseWorker(): void
    {
        $this->releaseWorker($this->getRedisClient());
        $this->releaseWorker($this->getPRedisClient());
    }

    public function testDBUsage(): void
    {
        $this->DBUsage($this->getRedisClient());
        $this->DBUsage($this->getPRedisClient());
    }

    public function testResolveWorkerOnNonReachableRedis(): void
    {
        $resolver = new RedisTimestepWorkerResolver(client: $this->getNonReachableRedisClient());
        $this->expectException(NoWorkerAvailableException::class);
        $timestamp = (int) (\microtime(true) * 1_000_000);
        $resolver->resolveWorkerId($timestamp, $timestamp * 1000);
    }

    private function workerResolve(\Redis|Client $redisClient): void
    {
        $workerBits = 8;

        $resolver = new RedisTimestepWorkerResolver(client: $redisClient, workersBits: $workerBits);
        $maxWorkers = $resolver->getConfiguration()->maxWorkers;

        $timestampUs = (int) (microtime(true) * 1_000_000);
        $nanoTime = $timestampUs * 1_000;

        $resolver->clearDatabase();

        for ($i = 0; $i < $maxWorkers; $i++) {
            $workerId = $resolver->resolveWorkerId($timestampUs, $nanoTime);
            $this::assertSame($i, $workerId);
        }

        $nanoTime += $resolver->getConfiguration()->timestepNs;

        for ($i = 0; $i < $maxWorkers; $i++) {
            $workerId = $resolver->resolveWorkerId($timestampUs, $nanoTime);
            $this::assertSame($i, $workerId);
        }

        // don't increment nanoTime, it should fail as there can't be more workers in this timestep
        $this->expectException(NoWorkerAvailableException::class);
        $resolver->resolveWorkerId($timestampUs, $nanoTime);
    }

    private function releaseWorker(\Redis|Client $redisClient): void
    {
        $resolver = new RedisTimestepWorkerResolver(client: $redisClient);
        $resolver->resolveWorkerId(1000, 1000);
        $this::assertTrue($resolver->releaseWorker());
        $this::assertSame(-1, $resolver->getCurrentWorkerId());
        $this::assertFalse($resolver->releaseWorker());
    }

    private function DBUsage(\Redis|Client $redisClient): void
    {
        $resolver = new RedisTimestepWorkerResolver(client: $redisClient);
        $resolver->resolveWorkerId(1000, 1000);
        $usage = $resolver->getDBUsage();
        $this::assertGreaterThan(0, $usage);
    }

    public function testResolverConfig(): void
    {
        $workersBits = 16;
        $sequenceBits = 8;
        $groupsBits = 1;
        $resolver = new RedisTimestepWorkerResolver(client: $this->getRedisClient(), workersBits: $workersBits, sequenceBits: $sequenceBits, groupsBits: $groupsBits);
        $this::assertTrue($resolver->dependsOnTimestamp());
        $this::assertSame(2, $resolver->getMaxWorkerResolveTrials());
        $this::assertSame($workersBits, $resolver->getConfiguration()->workersBits);
        $this::assertSame($sequenceBits, $resolver->getConfiguration()->sequenceBits);
        $this::assertSame($groupsBits, $resolver->getConfiguration()->groupsBits);
        $this::assertSame(0, $resolver->getConfiguration()->groupId);
        $this::assertSame(1 << $workersBits, $resolver->getConfiguration()->maxWorkers);
        $this::assertSame(1 << $sequenceBits, $resolver->getConfiguration()->maxSequence);
    }
}
