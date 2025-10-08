<?php

namespace Tests\Parallel;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;

/**
 * @internal
 *
 * @covers \Pvmlibs\FlexId\Resolvers\StaticWorkerResolver
 */
final class ParallelStaticWorkerTest extends TestCase
{
    public function testConcurrentGenerators(): void
    {
        $this->generate(30, 100, workersBits: 16, sequenceBits: 0);
        $this->generate(30, 1000, workersBits: 8, sequenceBits: 8);
    }

    private function generateIds(int $taskId, int $idsPerProcess, int $workersBits, int $sequenceBits): void
    {
        $dbRedis = self::getRedisClient();

        $resolver = new StaticWorkerResolver(
            workerHandlerFn: fn () => $taskId,
            workersBits: $workersBits,
            sequenceBits: $sequenceBits,
        );
        $generator = new FlexIdGenerator(
            workerResolver: $resolver,
        );

        $ids = [];

        for ($i = 0; $i < $idsPerProcess; $i++) {
            $ids[] = $generator->id();
        }

        $dbRedis->set("results{$taskId}", json_encode($ids));
    }

    /**
     * @throws \Exception
     */
    private function generate(int $taskCount, int $idsPerProcess, int $workersBits, int $sequenceBits): void
    {
        $dbRedis = self::getRedisClient();

        for ($i = 0; $i < $taskCount; $i++) {
            $pid = \pcntl_fork();

            if ($pid === -1) {
                exit('Error forking');
            }

            if ($pid === 0) {
                $this->generateIds($i, $idsPerProcess, $workersBits, $sequenceBits);
                exit;
            }
        }

        while (true) {
            if (\pcntl_waitpid(0, $status) === -1) {
                break;
            }
            usleep(1000);
        }

        $results = [];
        for ($i = 0; $i < $taskCount; $i++) {
            array_push($results, ...array_values(json_decode($dbRedis->get("results{$i}"), true)));
        }

        $results = \array_unique($results);
        $this::assertCount($idsPerProcess * $taskCount, $results);
    }

    private static function getRedisClient(): \Redis
    {
        return new \Redis(
            [
                'host' => 'flexid_valkey',
                'port' => 6379,
            ],
        );
    }
}
