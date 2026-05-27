<?php

declare(strict_types=1);

namespace Tests\Parallel;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\ShortRedisTimestepWorkerResolver;
use Tests\Internal\HasRedisClient;

/**
 * @internal
 */
final class ParallelRedisShortTimestepWorkerTest extends TestCase
{
    use HasRedisClient;

    public function testConcurrentGenerators(): void
    {
        $this->generate(16, 1000, workersBits: 7, sequenceBits: 5, timestampShift: 14); // timestep 65ms x 4096 -> 64k/s
        $this->generate(16, 150, workersBits: 6, sequenceBits: 6, timestampShift: 16); // timestep 268ms x 4096 -> 16k/s
    }

    private function generateIds(int $taskId, int $idsPerProcess, int $workersBits, int $sequenceBits, int $timestampShift): void
    {
        $dbRedis = $this->getRedisClient();

        $resolver = new ShortRedisTimestepWorkerResolver(
            client: $dbRedis,
            workersBits: $workersBits,
            sequenceBits: $sequenceBits,
            timestampBitshift: $timestampShift,
        );
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
    private function generate(int $taskCount, int $idsPerProcess, int $workersBits, int $sequenceBits, int $timestampShift): void
    {
        $dbRedis = $this->getRedisClient();
        $resolver = new ShortRedisTimestepWorkerResolver(client: $dbRedis);
        $resolver->clearDatabase();

        for ($i = 0; $i < $taskCount; $i++) {
            $pid = \pcntl_fork();

            if ($pid === -1) {
                exit('Error forking');
            }

            if ($pid === 0) {
                $this->generateIds($i, $idsPerProcess, $workersBits, $sequenceBits, $timestampShift);
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
