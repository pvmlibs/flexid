<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Resolvers;

use Pvmlibs\FlexId\Contracts\WorkerResolverContract;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\VO\IdConfiguration;

/**
 * Random worker resolver, it can guarantee ID uniqueness but only with conditions:
 * - only one process at a time is generating IDs
 * - multiple processes generating IDs at only one host, assuming every process fit in $pidBits and does not repeat (it will use pid % max pid)
 * In multi host environment there are no guarantees and in that case use only workerBits. Then collision probability is proportional to generation rate:
 * ids/sec * timestep[sek] / max workers, so e.g. at 1k id/s with workersBits 11 = 1000 * 0,000002048 ÷ 2048 = 0.0001% so
 * 0,001 collisions/s - 1 every ~16min with that constant generation rate.
 * Note that timestep depends on total bits used (workerBits + sequenceBits + groupsBits).
 * Can be used in small applications, where there is no Redis/Valkey database available or as fallback for other generator.
 */
class RandomWorkerResolver implements WorkerResolverContract
{
    private int $workerId = -1;
    private int $lastTimestepNs = 0;
    private int $counter = 0;
    private int $pid = 0;
    private int $workerPoolNr;
    private int $maxRandom;
    private readonly IdConfiguration $configuration;

    /**
     * @param int $workersBits here it means bits for randomness, this includes pidBits. Should be set to cover ~ clock resolution.
     * @param int $pidBits     lowers chance of collision in one host scenario. Set to fit max process count that can generate IDs but try not
     *                         to use all workerBits for better entropy. It could only work when pids are constant or will increment evenly,
     *                         so pid modulo [max pid count] will not overlap with other pid. In multi host with multiprocess application
     *                         better use just only workerBits and $pidBits=0.
     *
     * @throws \Exception
     */
    public function __construct(
        public readonly int $groupId = 0,
        public readonly int $workersBits = 11,
        public readonly int $groupsBits = 0,
        public readonly int $pidBits = 0,
        public readonly int $timestampOffset = 1735689600, // UTC 2025-01-01
    ) {
        $this->configuration = new IdConfiguration(
            workersBits: $this->workersBits,
            sequenceBits: 0, // it's too costly for this resolver
            groupsBits: $this->groupsBits,
            groupId: $this->groupId,
            // this kind of implement sequence - this way generator will ask for new worker on sequence overflow instead
            // of waiting till next timestep (in practice >~50us)
            useNewWorkerOnSequenceOverflow: true,
            timestampOffset: $this->timestampOffset,
        );

        if ($this->pidBits > $this->workersBits) {
            throw new \DomainException('PidBits must be lower or equal to workerBits.');
        }

        $randomBits = $this->workersBits - $this->pidBits;
        $this->maxRandom = 1 << $randomBits;

        if ($this->pidBits > 0) {
            $pid = \getmypid();
            if ($pid === false) {
                $pid = \random_int(0, (1 << $this->pidBits) - 1);
            } else {
                $pid %= 1 << $this->pidBits;
            }
            $this->pid = $pid << $randomBits;
        }

        $this->workerPoolNr = \random_int(0, $this->maxRandom - 1);
    }

    public function getCurrentWorkerId(): int
    {
        return $this->workerId;
    }

    /**
     * @throws \Exception
     */
    public function resolveWorkerId(int $currentTimeUs, int $currentTimestepNs): int
    {
        $this->workerPoolNr = ($this->workerPoolNr + 1) % $this->maxRandom;

        if ($currentTimestepNs !== $this->lastTimestepNs) {
            $this->lastTimestepNs = $currentTimestepNs;
            $this->counter = 1;
        } else {
            $this->counter++;
            if ($this->counter > $this->maxRandom) {
                // used all workers, wait until next timestep
                throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId);
            }
        }

        $this->workerId = $this->pid | $this->workerPoolNr;

        return $this->workerId;
    }

    public function releaseWorker(int $lastIdGenTimeUs = 0, int $lastTimeStepNs = 0, $nowUs = 0): bool
    {
        if ($this->workerId === -1) {
            return false;
        }

        $this->workerId = -1;

        return true;
    }

    public function getMaxWorkerResolveTrials(): int
    {
        return 2;
    }

    public function dependsOnTimestamp(): bool
    {
        return true;
    }

    public function getConfiguration(): IdConfiguration
    {
        return $this->configuration;
    }
}
