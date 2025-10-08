<?php

namespace Tests\Resolvers;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\Resolvers\RedisReservedWorkerResolver;
use Tests\Internal\hasRedisClient;

/**
 * @covers \Pvmlibs\FlexId\Resolvers\RedisReservedWorkerResolver
 *
 * @internal
 */
final class RedisReservedWorkerResolverTest extends TestCase
{
    use hasRedisClient;

    public function testWorkerResolve(): void
    {
        $this->workerResolve($this->getRedisClient());
        $this->workerResolve($this->getPRedisClient());
    }

    public function testWorkerResolveAfterTimeout(): void
    {
        $this->workerResolveAfterTimeout($this->getRedisClient());
        $this->workerResolveAfterTimeout($this->getPRedisClient());
    }

    public function testDBUsage(): void
    {
        $this->dbUsage($this->getRedisClient());
        $this->dbUsage($this->getPRedisClient());
    }

    public function testBulkResolving(): void
    {
        $this->bulkResolving($this->getRedisClient());
        $this->bulkResolving($this->getPRedisClient());
    }

    public function testResolveWorkerWrongCurrentTimeRedis(): void
    {
        // current time must be > timestampOffsetUs
        $resolver = new RedisReservedWorkerResolver(client: $this->getRedisClient());
        $this->expectException(\Exception::class);
        $resolver->resolveWorkerId($resolver->timestampOffsetUs - 1, 1000);
    }

    public function testNegativeTimestampOffset(): void
    {
        $this->expectException(\Exception::class);
        new RedisReservedWorkerResolver(client: $this->getRedisClient(), timestampOffsetUs: -1);
    }

    public function testResolveWorkerOnNonReachableRedis(): void
    {
        $resolver = new RedisReservedWorkerResolver(client: $this->getNonReachableRedisClient());
        $this->expectException(NoWorkerAvailableException::class);
        $timestamp = (int) (\microtime(true) * 1_000_000);
        $resolver->resolveWorkerId($timestamp, $timestamp * 1000);
    }

    private function dbUsage(\Redis|Client $redisClient): void
    {
        $timeoutMs = 5;
        $resolver = new RedisReservedWorkerResolver(client: $redisClient, workersBits: 0, TTLMs: $timeoutMs, minimalWorkerSeparationMs: 1);
        $workerSeparationMs = $resolver->getWorkerSeparationMs();
        $resolver->clearDatabase();

        $timestamp = (int) (\microtime(true) * 1_000_000);
        $nanoTime = $timestamp * 1_000;

        $resolver->resolveWorkerId($timestamp, $nanoTime);

        $usage = $resolver->getCurrentlyUsedWorkers();
        $this::assertSame(1, $usage['usedWorkers']);
        $this::assertNull($usage['lastLockUs']);
        $this::assertNull($usage['lastLockDate']);

        // with positive time offset it will be 0
        $usage = $resolver->getCurrentlyUsedWorkers($workerSeparationMs + 5);
        $this::assertSame(0, $usage['usedWorkers']);

        $resolver->releaseWorker();
        try {
            $resolver->resolveWorkerId($timestamp, $nanoTime);
        } catch (NoWorkerAvailableException $exception) {
        }

        $usage = $resolver->getCurrentlyUsedWorkers();
        $this::assertSame(1, $usage['usedWorkers']);
        $this::assertNotNull($usage['lastLockUs']);
        $this::assertNotNull($usage['lastLockDate']);

        $dbSize = $resolver->getDBUsage();
        $this::assertSame(1, $dbSize['workersSlotsWritten']);

        // should not remove yet
        $resolver->removeExpiredWorkers();
        $dbSize = $resolver->getDBUsage();
        $this::assertSame(1, $dbSize['workersSlotsWritten']);

        \usleep(($timeoutMs + $workerSeparationMs) * 1_000);
        $resolver->removeExpiredWorkers();
        $dbSize = $resolver->getDBUsage();
        $this::assertSame(0, $dbSize['workersSlotsWritten']);
    }

    private function workerResolve(\Redis|Client $redisClient): void
    {
        $timeoutMs = 10;
        $resolver = new RedisReservedWorkerResolver(client: $redisClient, workersBits: 1, TTLMs: $timeoutMs, minimalWorkerSeparationMs: 1);
        $workerSeparationMs = $resolver->getWorkerSeparationMs();

        $timestampUs = (int) (\microtime(true) * 1_000_000);
        $nanoTime = $timestampUs * 1_000;
        $resolver->clearDatabase();

        $workerId1 = $resolver->resolveWorkerId($timestampUs, $nanoTime);
        $this::assertSame(0, $workerId1);
        $this::assertSame($workerId1, $resolver->getCurrentWorkerId());

        // for this resolver it will return the same worker if still within timeout
        $workerId2 = $resolver->resolveWorkerId($timestampUs, $nanoTime);
        $this::assertSame(0, $workerId2);
        $this::assertSame($workerId2, $resolver->getCurrentWorkerId());

        $resolver->releaseWorker();
        $workerId3 = $resolver->resolveWorkerId($timestampUs, $nanoTime);
        $this::assertSame(1, $workerId3);

        // reached max reserved workers, calculate new timestamp
        $timestampUs = (int) (\microtime(true) * 1_000_000) + 1_000 * ($timeoutMs + $workerSeparationMs);
        $resolver->releaseWorker();

        $workerId1 = $resolver->resolveWorkerId($timestampUs, $nanoTime);
        $this::assertSame(0, $workerId1);
        $resolver->releaseWorker($timestampUs);
        $workerId2 = $resolver->resolveWorkerId($timestampUs, $nanoTime);
        $this::assertSame(1, $workerId2);
        $resolver->releaseWorker($timestampUs);

        // reached max reserved workers, without new timestamp it should throw error
        $this->expectException(NoWorkerAvailableException::class);
        $resolver->resolveWorkerId($timestampUs, $nanoTime);
    }

    private function workerResolveAfterTimeout(\Redis|Client $redisClient): void
    {
        $timeoutMs = 50;
        $resolver = new RedisReservedWorkerResolver(client: $redisClient, workersBits: 1, TTLMs: $timeoutMs, minimalWorkerSeparationMs: 10);
        $resolver->clearDatabase();

        $timestampUs = (int) (\microtime(true) * 1_000_000);
        $nanoTime = $timestampUs * 1_000;

        $workerId1 = $resolver->resolveWorkerId($timestampUs, $nanoTime);
        $this::assertSame(0, $workerId1);
        $this::assertSame($workerId1, $resolver->getCurrentWorkerId());
        $resolver->releaseWorker();

        // for this resolver it will return the same worker if still within timeout
        $workerId2 = $resolver->resolveWorkerId($timestampUs, $nanoTime);
        $this::assertSame(1, $workerId2);
        $this::assertSame($workerId2, $resolver->getCurrentWorkerId());
        $resolver->releaseWorker();

        // reached max reserved workers, wait and try again
        \usleep($resolver->getWorkerSeparationMs() * 1_000);
        $timestampUs = (int) (\microtime(true) * 1_000_000);
        $nanoTime = $timestampUs * 1_000;
        $workerId3 = $resolver->resolveWorkerId($timestampUs, $nanoTime);
        $this::assertSame(0, $workerId3);

        // simulate 2 other generators
        $resolver2 = new RedisReservedWorkerResolver(client: $redisClient, workersBits: 1, TTLMs: $timeoutMs, minimalWorkerSeparationMs: 10);
        $resolver2->resolveWorkerId($timestampUs, $nanoTime);

        // pool is ful, simulate timeout to resolve new worker
        $timestampUs = (int) (\microtime(true) * 1_000_000) + (($timeoutMs + $resolver->getWorkerSeparationMs()) * 1_000);
        $nanoTime = $timestampUs * 1_000;
        $resolver3 = new RedisReservedWorkerResolver(client: $redisClient, workersBits: 1, TTLMs: $timeoutMs, minimalWorkerSeparationMs: 10);
        $resolver3->resolveWorkerId($timestampUs, $nanoTime);
    }

    private function bulkResolving(\Redis|Client $redisClient): void
    {
        $timeoutMs = 1;
        $workerSeparationMs = 1;
        $resolver = new RedisReservedWorkerResolver(client: $redisClient, workersBits: 10, TTLMs: $timeoutMs, minimalWorkerSeparationMs: $workerSeparationMs);
        $resolver->clearDatabase();
        $maxWorkers = 1 << $resolver->getConfiguration()->workersBits;

        $ids = [];
        for ($i = 0; $i < $maxWorkers + 200; $i++) {
            $timestampUs = (int) (\microtime(true) * 1_000_000);
            $nanoTime = $timestampUs * 1_000;
            $ids[] = $resolver->resolveWorkerId($timestampUs, $nanoTime);
            $resolver->releaseWorker();
        }

        $this::assertTrue(\max($ids) < $maxWorkers); // @phpstan-ignore argument.type
        $this::assertTrue(\min($ids) >= 0); // @phpstan-ignore argument.type

        $ids = [];

        $resolver = new RedisReservedWorkerResolver(client: $redisClient, TTLMs: $timeoutMs, minimalWorkerSeparationMs: $workerSeparationMs);
        $resolver2 = new RedisReservedWorkerResolver(client: $redisClient, TTLMs: 100, minimalWorkerSeparationMs: $workerSeparationMs);
        $resolver->clearDatabase();

        /** @var list<RedisReservedWorkerResolver> $resolvers */
        $resolvers = [$resolver, $resolver2];

        for ($i = 0; $i < $maxWorkers + 200; $i++) {
            $timestampUs = (int) (\microtime(true) * 1_000_000);
            $nanoTime = $timestampUs * 1_000;
            $resolver = $resolvers[\rand(0, 1)];
            $ids[] = $resolver->resolveWorkerId($timestampUs, $nanoTime);
            $resolver->releaseWorker();
        }

        $this::assertTrue(\max($ids) < $maxWorkers); // @phpstan-ignore argument.type
        $this::assertTrue(\min($ids) >= 0); // @phpstan-ignore argument.type
    }

    public function testResolverConfig(): void
    {
        $workersBits = 16;
        $sequenceBits = 8;
        $groupsBits = 1;
        $resolver = new RedisReservedWorkerResolver(client: $this->getRedisClient(), workersBits: $workersBits, sequenceBits: $sequenceBits, groupsBits: $groupsBits);
        $this::assertFalse($resolver->dependsOnTimestamp());
        $this::assertSame(1, $resolver->getMaxWorkerResolveTrials());
        $this::assertSame($resolver->getConfiguration()->workersBits, $workersBits);
        $this::assertSame($resolver->getConfiguration()->sequenceBits, $sequenceBits);
        $this::assertSame($resolver->getConfiguration()->groupsBits, $groupsBits);
        $this::assertSame($resolver->getConfiguration()->groupId, 0);
    }

    public function testReleaseWorker(): void
    {
        $resolver = new RedisReservedWorkerResolver(client: $this->getRedisClient());
        $resolver->clearDatabase();

        $currentTime = (int) (\microtime(true) * 1_000_000);
        $nanoTime = $currentTime * 1_000;

        $resolver->resolveWorkerId($currentTime, $nanoTime);
        $this::assertTrue($resolver->releaseWorker());
        $this::assertSame(-1, $resolver->getCurrentWorkerId());
        $this::assertFalse($resolver->releaseWorker());

        $currentTime -= ($resolver->getTTLms() * 1000);
        $resolver->resolveWorkerId($currentTime, $nanoTime);
        $this::assertFalse($resolver->releaseWorker()); // after timeout, no need to deregister
    }
}
