<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Resolvers;

use Pvmlibs\FlexId\Contracts\WorkerResolverContract;
use Pvmlibs\FlexId\Exceptions\IdConfigurationException;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\VO\IdConfiguration;

/**
 * This resolver uses APCu as central source of truth, to produce globally unique worker IDs all processes must share
 * same APCu instance. It is possible with php-fpm but separate workers will not share the same APCu.
 * There are also framework that uses php cli and forking processes - this can also work but only within one host.
 * Remember to set ini config apc.enabled=1 and apc.enable_cli=1.
 * For subsequent process rerun, there is lock file which verifies if resolving worker takes place within previous
 * timestep to prevent possible duplicates, if it does it will throw NoWorkerAvailableException so generator can wait
 * until the next timestep.
 */
class ApcuTimestepWorkerResolver implements WorkerResolverContract
{
    private int $workerId = -1;
    private string $workersKey;

    private string $prefix = '_flexid_apcu_tw:';
    private readonly IdConfiguration $configuration;
    private readonly ?string $lockFileName;

    private int $lastTimeStepNs = 0;
    private int $lastGenTimeUs = 0;
    private int $lastWorkerTimeStepNs = 0;
    private int $lastWorkerGenTimeUs = 0;

    /**
     * @param int  $resolveWorkerTrials            How many tries to allow if there are no worker available. With no worker available (reached max workers in given timestep),
     *                                             each try will be separated by up to timestep time.
     * @param int  $timestepExpireSec              time in seconds after timestep entry with last worker id will expire and be removed.
     *                                             This time resolves problems with clock differences between hosts and synchronizes workers.
     * @param bool $useNewWorkerOnSequenceOverflow use new worker on sequence overflow within given timestep. By default, it allows for better performance in burst ID generation.
     *
     * @throws \Exception
     */
    public function __construct(
        public readonly int $groupId = 0,
        public readonly int $workersBits = 10,
        public readonly int $sequenceBits = 10,
        public readonly int $groupsBits = 0,
        public readonly bool $useNewWorkerOnSequenceOverflow = true,
        public readonly int $resolveWorkerTrials = 2,
        public readonly int $timestepExpireSec = 2,
        public readonly ?string $workerLockFilePath = '/tmp',
        public readonly int $timestampBitshift = 0,
        public readonly int $timestampOffset = 1735689600, // UTC 2025-01-01
    ) {
        if (\apcu_enabled() === false) {
            throw new IdConfigurationException('This resolver requires APCu extension');
        }

        $this->configuration = new IdConfiguration(
            workersBits: $this->workersBits,
            sequenceBits: $this->sequenceBits,
            groupsBits: $this->groupsBits,
            groupId: $this->groupId,
            useNewWorkerOnSequenceOverflow: $this->useNewWorkerOnSequenceOverflow,
            lockFilePath: $this->workerLockFilePath,
            timestampBitshift: $this->timestampBitshift,
            timestampOffset: $this->timestampOffset,
        );

        if ($this->configuration->lockFilePath !== null) {
            $this->lockFileName = \sprintf('%s/flex_id_apcu_ts_tb%d_to%d_gen.tmp', $this->configuration->lockFilePath, $this->timestampBitshift, $this->timestampOffset);
        } else {
            $this->lockFileName = null;
        }

        $this->setPrefix($this->prefix);

        $this->loadLockFile();
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
        $this->checkSubsequentRunWithForLock($currentTimeUs, $currentTimestepNs);

        $result = \apcu_inc(
            key: $this->workersKey . $currentTimestepNs,
            ttl: $this->timestepExpireSec,
        );

        if ($result === false) {
            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId, previous: new \Exception('Error in worker script'));
        }

        // it will start from 1
        $workerId = $result - 1;

        if ($workerId >= $this->configuration->maxWorkers) {
            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId);
        }

        $this->workerId = $workerId;

        return $this->workerId;
    }

    private function setPrefix(string $prefix): self
    {
        // concurrent workers with different time configurations should have separate keys
        $this->prefix = $prefix . "{$this->timestampBitshift}:{$this->timestampOffset}:";
        $this->workersKey = $this->prefix . 'ts_workers:' . $this->groupId . ':';

        return $this;
    }

    public function releaseWorker(int $lastIdGenTimeUs = 0, int $lastTimeStepNs = 0, int $nowUs = 0): bool
    {
        $this->lastWorkerTimeStepNs = $lastTimeStepNs;
        $this->lastWorkerGenTimeUs = $lastIdGenTimeUs;

        if ($this->workerId === -1) {
            return false;
        }

        $this->workerId = -1;

        return true;
    }

    public function __destruct()
    {
        // save lock file only on destroy
        if ($this->lockFileName !== null) {
            \file_put_contents(
                $this->lockFileName,
                \json_encode([
                    'lastTimestepNs' => $this->lastWorkerTimeStepNs,
                    'lastIdGenTimeUs' => $this->lastWorkerGenTimeUs,
                ]),
            );
        }
    }

    public function getMaxWorkerResolveTrials(): int
    {
        return $this->resolveWorkerTrials;
    }

    public function dependsOnTimestamp(): bool
    {
        return true;
    }

    public function getConfiguration(): IdConfiguration
    {
        return $this->configuration;
    }

    public function clearLock(): void
    {
        $this->lastTimeStepNs = 0;
        $this->lastGenTimeUs = 0;

        if ($this->lockFileName === null) {
            return;
        }

        if (\file_exists($this->lockFileName)) {
            \unlink($this->lockFileName);
        }
    }

    private function checkSubsequentRunWithForLock(int $currentTimeUs, int $currentTimestepNs): void
    {
        if ($currentTimeUs - $this->lastGenTimeUs - 1 > \intdiv($this->configuration->timestepNs, 1_000)) {
            return;
        }
        if ($this->lastTimeStepNs >= $currentTimestepNs) {
            $diffNs = ($this->lastTimeStepNs - $currentTimestepNs) + $this->configuration->timestepNs;
            $diffUs = (int) \ceil($diffNs / 1000);

            if ($diffUs < 1 || $diffUs > \max(1, 2 * \intdiv($this->configuration->timestepNs, 1_000))) {
                throw new \Exception(\sprintf('Too much time diff %d us on subsequent generate with same worker id %d', $diffUs, $this->workerId));
            }

            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId, lockTimeUs: $diffUs);
        }
    }

    private function loadLockFile(): void
    {
        if ($this->lockFileName !== null && \file_exists($this->lockFileName)) {
            $lastGenDataJson = @\file_get_contents($this->lockFileName);

            if ($lastGenDataJson !== false) {
                $lastGenData = \json_decode($lastGenDataJson, true);
                $this->lastTimeStepNs = (int) ($lastGenData['lastTimestepNs'] ?? 0);
                $this->lastGenTimeUs = (int) ($lastGenData['lastIdGenTimeUs'] ?? 0);
            }
        }
    }
}
