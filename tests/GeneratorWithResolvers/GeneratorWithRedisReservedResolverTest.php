<?php

declare(strict_types=1);

namespace Tests\GeneratorWithResolvers;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\RedisReservedWorkerResolver;
use Pvmlibs\FlexId\Resolvers\ShortRedisReservedWorkerResolver;
use Tests\Internal\HasRedisClient;

/**
 * @internal
 */
final class GeneratorWithRedisReservedResolverTest extends TestCase
{
    use HasRedisClient;

    public function testGenerate(): void
    {
        $this->generateIds($this->getRedisClient());
        $this->generateIds($this->getPRedisClient());
    }

    public function testGenerateShort(): void
    {
        $this->generateShortIds($this->getRedisClient());
        $this->generateShortIds($this->getPRedisClient());
    }

    public function testWorkersOverflow(): void
    {
        $this->overflowWorkers($this->getRedisClient());
        $this->overflowWorkers($this->getPRedisClient());
    }

    public function testWorkersTTL(): void
    {
        $this->workerTTL($this->getRedisClient(), 0);
        $this->workerTTL($this->getRedisClient(), 16);
    }

    private function generateIds(\Redis|Client $redisClient): void
    {
        $resolver = new RedisReservedWorkerResolver(client: $redisClient);
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 10000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $id = $generator->id();
            $ids[$id] = $id;
        }

        $this::assertCount($total, $ids, 'There are duplicates');
    }

    private function generateShortIds(\Redis|Client $redisClient): void
    {
        $resolver = new ShortRedisReservedWorkerResolver(
            client: $redisClient,
            workersBits: 0,
            sequenceBits: 12,
            useNewWorkerOnSequenceOverflow: false,
            timestampBitshift: 14,
        );
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 10000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $id = $generator->id();
            $ids[$id] = $id;
        }

        $this::assertCount($total, $ids, 'There are duplicates');
    }

    private function overflowWorkers(\Redis|Client $redisClient): void
    {
        $resolver = new RedisReservedWorkerResolver(client: $redisClient, workersBits: 10, sequenceBits: 0, groupsBits: 18, minimalWorkerSeparationMs: 200);
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 2000;

        $this->expectException(NoWorkerAvailableException::class);
        for ($i = 0; $i < $total; $i++) {
            $generator->id();
            $this::assertTrue($generator->releaseWorker());
        }
    }

    private function workerTTL(\Redis|Client $redisClient, int $timestampBitshift): void
    {
        $resolver = new RedisReservedWorkerResolver(
            client: $redisClient,
            workersBits: 0,
            sequenceBits: 10,
            groupsBits: 0,
            TTLMs: 70,
            minimalWorkerSeparationMs: 10,
            timestampBitshift: $timestampBitshift,
        );
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $ids = [];

        $ids[] = $generator->id();
        $lastTimeout = $resolver->getLastTimeoutUs();
        $workerId = $resolver->getCurrentWorkerId();

        // wait till timeout
        \usleep(70000);

        $ids[] = $generator->id();

        $this::assertCount(count($ids), \array_unique($ids));
        $this::assertSame($workerId, $resolver->getCurrentWorkerId());
        $this::assertNotSame($lastTimeout, $resolver->getLastTimeoutUs());
    }
}
