<?php

declare(strict_types=1);

namespace Tests\Parallel;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\ShortApcuTimestepWorkerResolver;
use Tests\Internal\hasRedisClient;

/**
 * @internal
 */
final class ParallelApcuShortTimestepWorkerTest extends TestCase
{
    use hasRedisClient;

    public function testConcurrentGenerators(): void
    {
        $this->generate(12, 200, workersBits: 3, sequenceBits: 7); // 65ms x 128 -> 2k/worker/s
    }

    private function generateIds(int $taskId, int $idsPerProcess, int $workersBits, int $sequenceBits): void
    {
        $dbRedis = $this->getRedisClient();

        $resolver = new ShortApcuTimestepWorkerResolver(workersBits: $workersBits, sequenceBits: $sequenceBits);
        $generator = new FlexIdGenerator(workerResolver: $resolver);

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
        $dbRedis = $this->getRedisClient();
        $resolver = new ShortApcuTimestepWorkerResolver();

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

        $this::assertCount($idsPerProcess * $taskCount, $results);
        $results = array_unique($results);
        $this::assertCount($idsPerProcess * $taskCount, $results, 'Found duplicate ids');
    }
}
