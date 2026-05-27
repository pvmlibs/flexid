<?php

declare(strict_types=1);

namespace Tests\Parallel;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\RandomWorkerResolver;
use Tests\Internal\HasRedisClient;

/**
 * @internal
 */
final class ParallelRandomWorkerTest extends TestCase
{
    use HasRedisClient;

    public function testConcurrentGenerators(): void
    {
        $this->generate(10, 100, 0);
    }

    public function testConcurrentGeneratorsWithPid(): void
    {
        $this->generate(10, 100, 7);
    }

    private function generateIds(int $taskId, int $idsPerProcess, int $workersBits, int $groupsBits, int $pidBits): void
    {
        $dbRedis = $this->getRedisClient();

        $resolver = new RandomWorkerResolver(
            workersBits: $workersBits,
            groupsBits: $groupsBits,
            pidBits: $pidBits,
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
    private function generate(int $taskCount, int $idsPerProcess, int $pidBits): void
    {
        $dbRedis = $this->getRedisClient();

        $workersBits = 11;
        $sequenceBits = 0;
        $groupsBits = 0;

        $start = hrtime(true);
        for ($i = 0; $i < $taskCount; $i++) {
            $pid = \pcntl_fork();

            if ($pid === -1) {
                exit('Error forking');
            }

            if ($pid === 0) {
                $this->generateIds($i, $idsPerProcess, $workersBits, $groupsBits, $pidBits);
                exit;
            }
        }

        while (true) {
            if (\pcntl_waitpid(0, $status) === -1) {
                break;
            }
            usleep(1000);
        }
        $end = hrtime(true);

        $seconds = ($end - $start) / 1e9;

        $results = [];
        for ($i = 0; $i < $taskCount; $i++) {
            array_push($results, ...array_values(json_decode($dbRedis->get("results{$i}"), true)));
        }

        // calculate collision probability only if no pidBits, otherwise assume IDs must be unique
        if ($pidBits === 0) {
            // collision rate depends on generation ratio and other parameters
            $timestepNs = 1 << ($workersBits + $sequenceBits + $groupsBits);
            $collisionProbability = ($taskCount * $idsPerProcess) / $seconds * ($timestepNs / 1e9) / (1 << $workersBits);
            $expectedCollisions = $collisionProbability * ($taskCount * $idsPerProcess);
        } else {
            $expectedCollisions = 0;
        }
        $this::assertCount($idsPerProcess * $taskCount, $results);

        $results = \array_unique($results);

        // allow max 5x statistical expected collisions
        $this::assertGreaterThanOrEqual($idsPerProcess * $taskCount - ceil(5 * $expectedCollisions), \count($results), 'Found too many duplicated ids');
    }
}
