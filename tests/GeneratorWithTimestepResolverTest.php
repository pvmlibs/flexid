<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\RandomWorkerResolver;
use Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver;
use Tests\Internal\hasRedisClient;

/**
 * @internal
 */
final class GeneratorWithTimestepResolverTest extends TestCase
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

    public function generateIds(\Redis|Client $redisClient): void
    {
        // in RandomWorkerResolver working alone there should be guaranteed uniqueness even in burst generation
        $resolver = new RedisTimestepWorkerResolver(client: $redisClient);
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 10000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $ids[] = $generator->id();
        }

        $this::assertCount($total, \array_unique($ids));
    }

    public function overflowWorkers(\Redis|Client $redisClient): void
    {
        $resolver = new RedisTimestepWorkerResolver(client: $redisClient, workersBits: 10, sequenceBits: 0, groupsBits: 18);
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 2000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $ids[] = $generator->id();
        }

        $this::assertCount($total, \array_unique($ids));
    }
}
