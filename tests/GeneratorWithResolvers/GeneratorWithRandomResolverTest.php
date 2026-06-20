<?php

declare(strict_types=1);

namespace Tests\GeneratorWithResolvers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\RandomWorkerResolver;

/**
 * @internal
 */
final class GeneratorWithRandomResolverTest extends TestCase
{
    public function testSingleWorker(): void
    {
        // in RandomWorkerResolver working alone there should be guaranteed uniqueness even in burst generation
        $resolver = new RandomWorkerResolver();
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = max(10000, $resolver->getConfiguration()->maxWorkers * 10);
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $id = $generator->id();
            $ids[$id] = $id;
        }

        $this::assertCount($total, $ids);
    }

    public function testWorkersOverflow(): void
    {
        $resolver = new RandomWorkerResolver(workersBits: 10, groupsBits: 18);
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $total = $resolver->getConfiguration()->maxWorkers * 2;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $id = $generator->id();
            $ids[$id] = $id;
        }

        $this::assertCount($total, $ids);
    }
}
