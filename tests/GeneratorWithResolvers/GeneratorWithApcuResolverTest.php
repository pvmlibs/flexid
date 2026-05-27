<?php

declare(strict_types=1);

namespace Tests\GeneratorWithResolvers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\ApcuTimestepWorkerResolver;
use Pvmlibs\FlexId\Resolvers\ShortApcuTimestepWorkerResolver;
use Tests\Internal\HasRedisClient;

/**
 * @internal
 */
final class GeneratorWithApcuResolverTest extends TestCase
{
    use HasRedisClient;

    public function testGenerate(): void
    {
        $resolver = new ApcuTimestepWorkerResolver();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 10000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $ids[] = $generator->id();
        }

        $this::assertCount($total, \array_unique($ids));
    }

    public function testGenerateShort(): void
    {
        $resolver = new ShortApcuTimestepWorkerResolver();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 16000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $ids[] = $generator->id();
        }

        $this::assertCount($total, \array_unique($ids));
    }

    public function testWorkersOverflow(): void
    {
        $resolver = new ApcuTimestepWorkerResolver(workersBits: 10, sequenceBits: 0, groupsBits: 18, resolveWorkerTrials: 1);
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = 3000;

        $this->expectException(NoWorkerAvailableException::class);
        for ($i = 0; $i < $total; $i++) {
            $generator->id();
            $this::assertTrue($generator->releaseWorker());
        }
    }
}
