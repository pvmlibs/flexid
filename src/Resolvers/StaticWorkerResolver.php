<?php

namespace Pvmlibs\FlexId\Resolvers;

use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\VO\IdConfiguration;

/**
 * Static worker resolver to provide arbitrary static worker id.
 * Make sure worker id will fit into workerBits.
 * This resolver can provide unique IDs as long as the same, unique worker id is assigned within the same host/container.
 * There can be still corner cases where processes (old and new one) subsequently generate id with the same worker id within
 * the same timestep - that case is handled by providing lockFilePath so next process will wait till next timestep.
 */
class StaticWorkerResolver implements WorkerResolverContract
{
    private int $workerId = -1;
    private readonly IdConfiguration $configuration;
    private readonly string $lockFileTemplate;

    /**
     * Lock resolver class instance to unique worker id to prevent multiple instances with same worker id.
     *
     * @var array<int, bool>
     */
    private static array $resolverLock = [];

    /**
     * @param \Closure(): int $workerHandlerFn method that will be called when retrieving worker id. It will be called max once.
     */
    public function __construct(
        public readonly \Closure $workerHandlerFn,
        public readonly int $groupId = 0,
        public readonly int $workersBits = 16,
        public readonly int $sequenceBits = 0,
        public readonly int $groupsBits = 0,
        public readonly ?string $workerLockFilePath = '/tmp',
    ) {
        $this->configuration = new IdConfiguration(
            workersBits: $this->workersBits,
            sequenceBits: $this->sequenceBits,
            groupsBits: $this->groupsBits,
            groupId: $this->groupId,
            useNewWorkerOnSequenceOverflow: false,
            lockFilePath: $this->workerLockFilePath,
        );
        $this->lockFileTemplate = '%s/flex_id_static_w%d_gen.tmp';
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
        if ($this->workerId === -1) {
            $this->workerId = ($this->workerHandlerFn)();
            if (\array_key_exists($this->workerId, self::$resolverLock)) {
                throw new \Exception('Incorrect use of StaticWorkerResolver - should be only one instance with the same worker id');
            }
            self::$resolverLock[$this->workerId] = true;

            $this->checkSubsequentRunWithSameWorkerAndSameTimestep($currentTimeUs, $currentTimestepNs);
        }

        return $this->workerId;
    }

    public function releaseWorker(?int $lastIdGenTimeUs = null, ?int $lastTimeStepNs = null): bool
    {
        if ($this->workerId !== -1 && $this->configuration->lockFilePath !== null) {
            $fileName = \sprintf($this->lockFileTemplate, $this->configuration->lockFilePath, $this->workerId);

            file_put_contents(
                $fileName,
                \json_encode([
                    'lastTimestepNs' => $lastTimeStepNs,
                    'lastIdGenTimeUs' => $lastIdGenTimeUs,
                ]),
            );
        }

        return true;
    }

    public function __destruct()
    {
        if ($this->workerId !== -1) {
            unset(self::$resolverLock[$this->workerId]);
        }
    }

    public function getMaxWorkerResolveTrials(): int
    {
        // 2 as we will try again if checkSubsequentRunWithSameWorkerAndSameTimestep validation fails
        return 2;
    }

    public function dependsOnTimestamp(): bool
    {
        return false;
    }

    public function getConfiguration(): IdConfiguration
    {
        return $this->configuration;
    }

    /**
     * This uses file to store the last timestep data for given worker, so workers id within same host/container
     * can be protected in scenario: create class -> get id -> destroy class -> create class -> get id with same
     * worker id and within the same timestep. This corner case can be reached especially with bigger timestep and
     * very fast class instance recreate.
     * This does not use file locks deliberately to eliminate accidentally left locks due to e.g. OOM event - it is NOT
     * supposed to work in concurrent environment - such as multiple processes with the same worker id, which would be
     * fundamentally wrong with this resolver and lock won't help anyway.
     * Also, locks don't work on every filesystem.
     *
     * @throws NoWorkerAvailableException|\Exception
     */
    private function checkSubsequentRunWithSameWorkerAndSameTimestep(int $currentTimeUs, int $currentTimestepNs): void
    {
        $fileName = \sprintf($this->lockFileTemplate, $this->configuration->lockFilePath, $this->workerId);
        $lastGenDataJson = @\file_get_contents($fileName);

        if ($lastGenDataJson !== false) {
            $lastGenData = \json_decode($lastGenDataJson, true);
            $lastTimeStepNs = (int) ($lastGenData['lastTimestepNs'] ?? 0);
            $lastGenTimeUs = (int) ($lastGenData['lastIdGenTimeUs'] ?? 0);

            if ($currentTimeUs - $lastGenTimeUs - 1 > \intdiv($this->configuration->timestepNs, 1000)) {
                return;
            }
            if ($lastTimeStepNs >= $currentTimestepNs) {
                $diffNs = ($lastTimeStepNs - $currentTimestepNs) + $this->configuration->timestepNs;
                $diffUs = (int) \ceil($diffNs / 1000);

                if ($diffUs < 1 || $diffUs > max(1, 2 * \intdiv($this->configuration->timestepNs, 1_000))) {
                    throw new \Exception(\sprintf('Too much time diff %dus on subsequent generate with same worker id %d', $diffUs, $this->workerId));
                }

                throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId, lockTimeUs: $diffUs);
            }
        }
    }

    public function clearLock(): void
    {
        if ($this->configuration->lockFilePath === null) {
            return;
        }

        if ($this->workerId === -1) {
            $workerId = ($this->workerHandlerFn)();
        } else {
            $workerId = $this->workerId;
        }

        $fileName = \sprintf($this->lockFileTemplate, $this->configuration->lockFilePath, $workerId);
        if (\file_exists($fileName)) {
            \unlink($fileName);
        }
    }
}
