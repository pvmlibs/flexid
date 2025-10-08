<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;

/**
 * @internal
 */
final class GeneratorWithStaticResolverTest extends TestCase
{
    public function testSingleWorker(): void
    {
        $workerId = 200;
        $resolver = new StaticWorkerResolver(workerHandlerFn: fn () => $workerId);
        $generator = new FlexIdGenerator(workerResolver: $resolver);

        $id = $generator->id();
        $this::assertSame($workerId, $generator->getWorkerIdFromId($id));

        $id2 = $generator->id();
        $this::assertSame($workerId, $generator->getWorkerIdFromId($id2));
        $this::assertNotSame($id, $id2);

        $total = 1000;
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $ids[] = $generator->id();
        }

        $this::assertCount($total, \array_unique($ids));
    }

    public function testSubsequentProcessGenerate(): void
    {
        $workerId = 200;
        $ids = [];
        $total = 100;

        // simulate cyclic process recreate with the same worker id
        for ($i = 0; $i < $total; $i++) {
            $resolver = new StaticWorkerResolver(workerHandlerFn: fn () => $workerId, workersBits: 20);
            $generator = new FlexIdGenerator(workerResolver: $resolver);
            $ids[] = $generator->id();
        }

        $this::assertCount($total, \array_unique($ids));
    }
}
