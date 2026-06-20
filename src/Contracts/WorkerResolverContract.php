<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\VO\IdConfiguration;

interface WorkerResolverContract
{
    /**
     * @param int $currentTimeUs     current unix timestamp in microseconds, includes offsets
     * @param int $currentTimestepNs current ID timestamp in nanoseconds, with offset
     *
     * @throws NoWorkerAvailableException
     */
    public function resolveWorkerId(int $currentTimeUs, int $currentTimestepNs): int;
    public function getCurrentWorkerId(): int;

    /**
     * @param int $lastIdGenTimeUs unix timestamp in microseconds of last ID gen, includes offsets
     * @param int $lastTimeStepNs  last timestep of generated ID, includes offsets
     * @param int $nowUs           unix timestamp in microseconds, includes offsets
     */
    public function releaseWorker(int $lastIdGenTimeUs = 0, int $lastTimeStepNs = 0, int $nowUs = 0): bool;
    public function getMaxWorkerResolveTrials(): int;

    /**
     * @return bool if worker depends on $timestepNs while resolving worker, it needs to return true. This will provide
     *              guarantees of unique ID generation for generator, so getNewWorkerId will be called when timestep will change
     */
    public function dependsOnTimestamp(): bool;
    public function getConfiguration(): IdConfiguration;
}
