<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\RedisReservedWorkerResolver;
use Tests\Internal\hasRedisClient;

/**
 * @internal
 */
final class GeneratorWithReservedResolverTest extends TestCase
{
    use hasRedisClient;

    public function testGenerate(): void
    {
        $this->generateIds($this->getRedisClient());
        $this->generateIds($this->getPRedisClient());
    }

    public function testWorkersOverflow(): void
    {
        $this->overflowWorkers($this->getRedisClient());
        $this->overflowWorkers($this->getPRedisClient());
    }

    private function generateIds(\Redis|Client $redisClient): void
    {
        $resolver = new RedisReservedWorkerResolver(client: $redisClient);
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 10000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $ids[] = $generator->id();
        }

        $this::assertCount($total, \array_unique($ids));
    }

    private function overflowWorkers(\Redis|Client $redisClient): void
    {
        $resolver = new RedisReservedWorkerResolver(client: $redisClient, workersBits: 10, sequenceBits: 0, groupsBits: 18, minimalWorkerSeparationMs: 200);
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 3000;

        $this->expectException(NoWorkerAvailableException::class);
        for ($i = 0; $i < $total; $i++) {
            $generator->id();
            $this::assertTrue($generator->releaseWorker());
        }
    }
}
