<?php

declare(strict_types=1);

namespace Tests\Parallel;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\ShortRedisReservedWorkerResolver;
use Tests\Internal\hasRedisClient;

/**
 * @internal
 */
final class ParallelRedisShortReservedWorkerTest extends TestCase
{
    use hasRedisClient;

    public function testConcurrentGenerators(): void
    {
        // $this->markTestSkipped('must be revisited.');
        $this->generate(2, 1000, workersBits: 0, sequenceBits: 11, timestampShift: 14, ttl: 200); // timestep 33ms x 2048 -> 64k/worker/s
        $this->generate(4, 200, workersBits: 4, sequenceBits: 7, timestampShift: 14, ttl: 1000); // timestep 33ms x 128 -> 4k/worker/s
        $this->generate(4, 200, workersBits: 3, sequenceBits: 7, timestampShift: 17, ttl: 200); // timestep 134ms x 128 -> 1k/worker/s
        $this->generate(4, 600, workersBits: 2, sequenceBits: 8, timestampShift: 10, ttl: 1000, newWorkerOnOverflow: true); // timestep 67ms x 256 -> 4k/worker/s
    }

    private function generateIds(
        int $taskId,
        int $idsPerProcess,
        int $workersBits,
        int $sequenceBits,
        int $timestampShift,
        int $ttl,
        bool $newWorkerOnOverflow,
    ): void {
        $dbRedis = $this->getRedisClient();

        $resolver = new ShortRedisReservedWorkerResolver(
            client: $dbRedis,
            workersBits: $workersBits,
            sequenceBits: $sequenceBits,
            useNewWorkerOnSequenceOverflow: $newWorkerOnOverflow,
            TTLMs: $ttl,
            resolveWorkerTrials: 4,
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
    private function generate(
        int $taskCount,
        int $idsPerProcess,
        int $workersBits,
        int $sequenceBits,
        int $timestampShift,
        int $ttl,
        bool $newWorkerOnOverflow = false,
    ): void {
        $dbRedis = $this->getRedisClient();
        $resolver = new ShortRedisReservedWorkerResolver(
            client: $dbRedis,
            workersBits: $workersBits,
            sequenceBits: $sequenceBits,
            useNewWorkerOnSequenceOverflow: $newWorkerOnOverflow,
            TTLMs: $ttl,
            timestampBitshift: $timestampShift,
        );
        $resolver->clearDatabase();

        for ($i = 0; $i < $taskCount; $i++) {
            $pid = \pcntl_fork();

            if ($pid === -1) {
                exit('Error forking');
            }

            if ($pid === 0) {
                $this->generateIds($i, $idsPerProcess, $workersBits, $sequenceBits, $timestampShift, $ttl, $newWorkerOnOverflow);
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
