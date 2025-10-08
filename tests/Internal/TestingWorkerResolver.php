<?php

namespace Tests\Internal;

use Pvmlibs\FlexId\Resolvers\WorkerResolverContract;
use Pvmlibs\FlexId\Resolvers\WorkerResolverHasTTLContract;
use Pvmlibs\FlexId\VO\IdConfiguration;

class TestingWorkerResolver implements WorkerResolverContract, WorkerResolverHasTTLContract
{
    /**
     * @var \Closure(): int
     */
    private \Closure $workerIdFn;
    private int $workerId = -1;
    private int $resolveTimeUs = 0;

    public function __construct(
        private readonly int $workerTimeoutMs = 2,
        private readonly int $groupId = 0,
        private readonly int $workersBits = 10,
        private readonly int $sequenceBits = 8,
        private readonly int $groupsBits = 0,
        private readonly bool $useNewWorkerOnSequenceOverflow = false,
        private readonly bool $dependsOnTimestep = false,
        private readonly int $resolveTrials = 1,
    ) {
        $this->workerIdFn = fn () => -1;
    }

    public function resolveWorkerId(int $currentTimeUs, int $currentTimestepNs): int
    {
        if ($this->resolveTimeUs > 0) {
            usleep($this->resolveTimeUs);
        }

        $this->workerId = ($this->workerIdFn)();

        return $this->workerId;
    }

    public function getCurrentWorkerId(): int
    {
        return $this->workerId;
    }

    public function getTTLms(): int
    {
        return $this->workerTimeoutMs;
    }

    public function releaseWorker(int $lastIdGenTimeUs = 0, int $lastTimeStepNs = 0): bool
    {
        return true;
    }

    /**
     * @param \Closure(): int $workerIdFn
     */
    public function setWorker(\Closure $workerIdFn): self
    {
        $this->workerIdFn = $workerIdFn;

        return $this;
    }

    public function setResolveTime(int $timeMs): self
    {
        $this->resolveTimeUs = $timeMs * 1_000;

        return $this;
    }

    public function getMaxWorkerResolveTrials(): int
    {
        return $this->resolveTrials;
    }

    public function dependsOnTimestamp(): bool
    {
        return $this->dependsOnTimestep;
    }

    public function getConfiguration(): IdConfiguration
    {
        return new IdConfiguration(
            workersBits: $this->workersBits,
            sequenceBits: $this->sequenceBits,
            groupsBits: $this->groupsBits,
            groupId: $this->groupId,
            useNewWorkerOnSequenceOverflow: $this->useNewWorkerOnSequenceOverflow,
        );
    }
}
