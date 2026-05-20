<?php

declare(strict_types=1);

namespace Tests\GeneratorWithResolvers;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver;
use Pvmlibs\FlexId\Resolvers\ShortRedisTimestepWorkerResolver;
use Tests\Internal\hasRedisClient;

/**
 * @internal
 */
final class GeneratorWithRedisTimestepResolverTest extends TestCase
{
    use hasRedisClient;

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

    public function generateIds(\Redis|Client $redisClient): void
    {
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

    private function generateShortIds(\Redis|Client $redisClient): void
    {
        $resolver = new ShortRedisTimestepWorkerResolver(client: $redisClient, timestampBitshift: 14);
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 20000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $ids[] = $generator->id();
        }

        $this::assertCount($total, \array_unique($ids));
    }

    public function overflowWorkers(\Redis|Client $redisClient): void
    {
        $resolver = new RedisTimestepWorkerResolver(client: $redisClient, workersBits: 10, sequenceBits: 0, groupsBits: 18, resolveWorkerTrials: 1);
        $resolver->clearDatabase();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 2000;

        $this->expectException(NoWorkerAvailableException::class);
        for ($i = 0; $i < $total; $i++) {
            $generator->id();
        }
    }
}
