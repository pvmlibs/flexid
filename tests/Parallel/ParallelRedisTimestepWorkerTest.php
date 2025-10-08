<?php

namespace Tests\Parallel;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver;

/**
 * @internal
 *
 * @covers \Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver
 */
final class ParallelRedisTimestepWorkerTest extends TestCase
{
    public function testConcurrentGenerators(): void
    {
        $this->generate(30, 2000, workersBits: 18, sequenceBits: 10);
        $this->generate(30, 1000, workersBits: 10, sequenceBits: 8);
    }

    private function generateIds(int $taskId, int $idsPerProcess, int $workersBits, int $sequenceBits): void
    {
        $dbRedis = self::getRedisClient();

        $resolver = new RedisTimestepWorkerResolver(client: $dbRedis, workersBits: $workersBits, sequenceBits: $sequenceBits);
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
        $dbRedis = self::getRedisClient();
        $resolver = new RedisTimestepWorkerResolver(client: $dbRedis);
        $resolver->clearDatabase();

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

    private static function getRedisClient(): \Redis
    {
        return new \Redis(
            [
                'host' => (string) \getenv('TESTING_REDIS_HOST'),
                'port' => (int) \getenv('TESTING_REDIS_PORT'),
            ],
        );
    }
}
