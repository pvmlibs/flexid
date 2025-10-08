<?php

namespace Pvmlibs\FlexId;

use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\Resolvers\WorkerResolverContract;
use Pvmlibs\FlexId\Resolvers\WorkerResolverHasTTLContract;
use Pvmlibs\FlexId\VO\IdConfiguration;

/**
 * 64-bit integer ID generator with focus on performance and flexibility.
 * Best to use as singleton for optimal performance.
 * IDs can be generated up to ~292 years from timestampOffset.
 * Use worker resolver that best suit your needs.
 */
class FlexIdGenerator
{
    private int $timestepMetaData = 0;
    public int $sequence = 0;
    private int $lastWorkerResolveNanoTimeNs = 0;
    private int $lastNanoTimeStep = 0;
    private int $fallbackGeneratorUseUntilUs = 0;
    private int $workerId = -1;
    private int $lastWorkerId = -1;
    private int $lastIdGenTimeUs = 0;
    private int $timestampOffsetUs = 0;
    private readonly int $metadataBitsMask;
    private readonly int $workerTTLns;
    private readonly bool $workerDependOnTimestep;
    public readonly IdConfiguration $resolverIdConfiguration;

    /**
     * @param int              $timestampOffset   offset in seconds (UNIX timestamp) where id starts. From this date maximum id range is applied.
     * @param ?FlexIdGenerator $fallbackGenerator defines fallback generator, in case of this generator throws NoWorkerAvailableException fallback is used. Can be used in cascade.
     * @param int              $sleepThresholdNs  defines minimum time in nanoseconds to next timestep on sequence overflow when generator will sleep. This is for balance between performance and CPU efficiency.
     *                                            During this time CPU usage will be high but will do job sooner. Smallest available sleep is usually > 50000 ns.
     *
     * @throws \Exception
     */
    public function __construct(
        private readonly WorkerResolverContract $workerResolver,
        public readonly int $timestampOffset = 1735689600, // UTC 2025-01-01
        private readonly ?FlexIdGenerator $fallbackGenerator = null,
        private readonly int $sleepThresholdNs = 10000,
    ) {
        if ($this->timestampOffset < 0) {
            throw new \DomainException('Timestamp offset must be >= 0');
        }

        $this->resolverIdConfiguration = $this->workerResolver->getConfiguration();

        if ($this->resolverIdConfiguration->workersBits === 0 && $this->resolverIdConfiguration->useNewWorkerOnSequenceOverflow) {
            throw new \DomainException('useNewWorkerOnSequenceOverflow needs more workerBits than 0');
        }

        $this->metadataBitsMask = -1 << $this->resolverIdConfiguration->totalMetaDataBits;

        $minimumWorkerTTLms = (int) ceil($this->resolverIdConfiguration->timestepNs / 1e6);
        if ($this->workerResolver instanceof WorkerResolverHasTTLContract) {
            $workerTTLms = $this->workerResolver->getTTLms();
        } else {
            $workerTTLms = \intdiv(PHP_INT_MAX, 1_000_000);
        }

        if ($workerTTLms < $minimumWorkerTTLms) {
            throw new \DomainException("Worker TTL must be at least {$minimumWorkerTTLms} ms to guarantee uniqueness for given parameters");
        }
        // convert to nanoseconds, make sure we have at least 1 timestep margin in ID generation
        $this->workerTTLns = max(1, $workerTTLms - $minimumWorkerTTLms) * 1_000_000;
        $this->workerDependOnTimestep = $this->workerResolver->dependsOnTimestamp();
        $this->timestampOffsetUs = $this->timestampOffset * 1_000_000;
    }

    public function __destruct()
    {
        $this->releaseWorker();
    }

    public function id(bool $reResolveWorker = false): int
    {
        $nowUs = (int) (\microtime(true) * 1_000_000);
        $nanoTimeStep = (($nowUs - $this->timestampOffsetUs) * 1_000) & $this->metadataBitsMask;

        // handle time drift backwards
        if (($this->lastIdGenTimeUs + $this->timestampOffsetUs) > $nowUs) {
            if ($this->timestampOffsetUs > $nowUs) {
                throw new \DomainException('Timestamp offset cannot be future date');
            }
            $diffUs = $this->lastIdGenTimeUs - $nowUs;
            if ($diffUs > 1e5) { // up to 100ms
                throw new \UnexpectedValueException(\sprintf('Time on server is too unstable, 100 ms diff in last %d ms', $this->workerTTLns / 1e6));
            }
            \usleep($diffUs);

            return $this->id(true);
        }

        if ($nowUs < $this->fallbackGeneratorUseUntilUs && $this->fallbackGenerator !== null) {
            return $this->fallbackGenerator->id();
        }

        if ((($nanoTimeStep - $this->lastWorkerResolveNanoTimeNs) >= $this->workerTTLns)
            || ($this->resolverIdConfiguration->maxSequence === 1 && $this->resolverIdConfiguration->useNewWorkerOnSequenceOverflow) // perf optimization when no sequence, avoids later recursive call
            || $reResolveWorker || $this->workerId === -1
            || (($this->lastWorkerResolveNanoTimeNs !== $nanoTimeStep) && $this->workerDependOnTimestep) // perf optimization, avoids later recursive call
        ) {
            try {
                [$nowUs, $nanoTimeStep] = $this->resolveWorker($nowUs, $nanoTimeStep);
            } catch (NoWorkerAvailableException $exception) {
                if ($this->fallbackGenerator !== null) {
                    $this->fallbackGeneratorUseUntilUs = $nowUs + $exception->lockTimeUs;

                    return $this->fallbackGenerator->id();
                }
                throw $exception;
            }
        }

        $nowUsWithOffset = $nowUs - $this->timestampOffsetUs;
        $nanoTimeRef = $nanoTimeStep;

        if ($this->sequence === $this->resolverIdConfiguration->maxSequence) {
            $nextTimestep = $this->lastNanoTimeStep + $this->resolverIdConfiguration->timestepNs;

            while ($nanoTimeStep === $this->lastNanoTimeStep) {
                if ($this->resolverIdConfiguration->useNewWorkerOnSequenceOverflow
                    && $this->workerId !== $this->lastWorkerId) { // when got the same worker after release, then wait to prevent loop
                    // release to prevent getting the same by chance (so we can reset sequence)
                    $this->releaseWorker();

                    return $this->id(true);
                }

                // Calculate time to next timestep, for performance it's better to wait a few cycles than to sleep
                // but for CPU usage it's better to sleep for longer periods
                if ($nextTimestep - ($nowUsWithOffset * 1_000) > $this->sleepThresholdNs) {
                    \usleep(10);
                }

                $nowUsWithOffset = (int) (\microtime(true) * 1_000_000) - $this->timestampOffsetUs;
                $nanoTimeStep = ($nowUsWithOffset * 1_000) & $this->metadataBitsMask;
            }
        }

        if ($nanoTimeStep !== $this->lastNanoTimeStep) {
            $this->sequence = 0;
            if ($nanoTimeStep - $this->lastWorkerResolveNanoTimeNs >= $this->workerTTLns) {
                // increment only 1 timestep if we exceeded timeout when waiting for worker
                // generator timeout is at least 1 timestep lower than worker so we will be still within timout
                $nanoTimeStep = $nanoTimeRef + $this->resolverIdConfiguration->timestepNs;
                // make sure to update also nowUsWithOffset as we use it for lastIdGenTimeNs
                $nowUsWithOffset = (int) (\microtime(true) * 1_000_000) - $this->timestampOffsetUs;
            }
        }

        // if resolver depends on timestamp to provide worker, if timestamp has changed since the time of resolving worker
        // we need to reresolve worker for new timestep
        if (($this->lastWorkerResolveNanoTimeNs !== $nanoTimeStep) && $this->workerDependOnTimestep) {
            return $this->id(true);
        }

        $this->lastNanoTimeStep = $nanoTimeStep;
        $this->lastIdGenTimeUs = $nowUsWithOffset;

        return $nanoTimeStep | $this->timestepMetaData | ($this->sequence++ << $this->resolverIdConfiguration->groupsBits);
    }

    /**
     * More performant method for getting chunks of IDs, works best when ID configuration has set sequence bits,
     * the more, the better and in massive ID range generation.
     * When e.g. used for inserting data into database, use $count <= $this->resolverIdConfiguration->maxSequence for most optimal usage, then
     * when DB is processing this chunk, we are very likely not to wait for next timestep.
     *
     * @return list<int>
     */
    public function bulkIds(int $count): array
    {
        $counter = 0;
        $ids = [];

        do {
            $id = $this->id();
            $ids[] = $id;
            $counter++;
            $baseId = ($id & $this->metadataBitsMask) | $this->timestepMetaData;
            while ($this->sequence < $this->resolverIdConfiguration->maxSequence && $counter < $count) {
                $ids[] = $baseId | ($this->sequence++ << $this->resolverIdConfiguration->groupsBits);
                $counter++;
            }
        } while ($counter < $count);

        return $ids;
    }

    /**
     * @return array<int, int>
     *
     * @throws NoWorkerAvailableException|\Exception
     */
    private function resolveWorker(int $nowUs, int $nanoTimeStep): array
    {
        $tries = 0;
        $exception = null;
        $workerId = null;
        $lockTimeUs = 0;

        while (true) {
            try {
                $workerId = $this->workerResolver->resolveWorkerId(
                    currentTimeUs: $nowUs,
                    currentTimestepNs: $nanoTimeStep,
                );
            } catch (NoWorkerAvailableException $exception) {
                $tries++;
                if ($exception->lockTimeUs === 0) {
                    // by default wait until next timestep
                    $nextTimestep = $nanoTimeStep + $this->resolverIdConfiguration->timestepNs;
                    $lockTimeUs = (int) ceil(($nextTimestep - ($nowUs - $this->timestampOffsetUs) * 1_000) / 1_000);
                } else {
                    $lockTimeUs = $exception->lockTimeUs;
                }
                if ($tries < $this->workerResolver->getMaxWorkerResolveTrials()) {
                    \usleep($lockTimeUs);
                    $nowUs = (int) (\microtime(true) * 1_000_000);
                    $nanoTimeStep = (($nowUs - $this->timestampOffsetUs) * 1_000) & $this->metadataBitsMask;
                    continue;
                }
            }
            break;
        }

        if ($workerId === null && $exception !== null) {
            throw new NoWorkerAvailableException($exception->maxWorkers, $exception->groupId, $lockTimeUs);
        }

        if ($workerId >= $this->resolverIdConfiguration->maxWorkers || $workerId < 0 || $workerId === null) {
            throw new \DomainException(\sprintf('Worker resolver return id %d out of range 0-%d', $workerId, $this->resolverIdConfiguration->maxWorkers - 1));
        }

        $this->lastWorkerId = $this->workerId;

        if ($this->workerId !== $workerId) {
            // reset sequence but only when worker has changed
            $this->sequence = 0;
            $this->workerId = $workerId;
        }

        $groupAndSequenceBits = $this->resolverIdConfiguration->groupsBits + $this->resolverIdConfiguration->sequenceBits;
        $this->timestepMetaData = ($workerId << $groupAndSequenceBits) | $this->resolverIdConfiguration->groupId;
        $this->lastWorkerResolveNanoTimeNs = $nanoTimeStep;

        return [$nowUs, $nanoTimeStep];
    }

    /**
     * Use in comparison (e.g. in DB scans) only with IDs generated with the same timestampOffset.
     * To be comparable between IDs on millisecond level, the sum of id metadata bits should be < 19 (timestep 0,262ms).
     * If there are more bits, timestamp precision will be less precise.
     * If you use different ID metadata bits configurations, use one with the largest bits count.
     *
     * @throws \Exception
     */
    public function toDate(int $id): \DateTime
    {
        // clear metadata bits
        $id &= $this->metadataBitsMask;
        $id /= 1e9;
        $seconds = \floor($id);
        $microseconds = $id - $seconds;

        $seconds += $this->timestampOffset;
        $microseconds = \number_format($microseconds, 3); // 1 ms precision

        $date = \DateTime::createFromFormat('U 0.u', "{$seconds} {$microseconds}", new \DateTimeZone('UTC'));

        if ($date === false) {
            throw new \DomainException('Unable to parse date from id ' . $id);
        }

        return $date;
    }

    /**
     * Use in comparison only with IDs generated with the same timestampOffset.
     */
    public function fromDate(\DateTime $date): int
    {
        // prepare timestamp with 1ms precision
        $timestamp = \intdiv(
            intval($date->setTimezone(new \DateTimeZone('UTC'))->format('Uu')),
            1_000,
        ) - $this->timestampOffset * 1_000;

        // pad to nanoseconds
        return $timestamp * 1_000_000 & $this->metadataBitsMask;
    }

    /**
     * @return array<string, string|int|float>
     *
     * @throws \Exception
     */
    public function info(): array
    {
        $gatherClockDataFn = function (callable $fn, array &$data): void {
            $lastStep = $fn();
            $data = [];

            for ($i = 1; $i < 100; $i++) {
                $currentStep = $fn();
                $data[] = $currentStep - $lastStep;
                $lastStep = $currentStep;
            }
            \sort($data);
        };
        // get statistic data about system clock
        $clockMicrotimeSteps = [];

        $gatherClockDataFn(fn () => \microtime(true) * 1_000_000, $clockMicrotimeSteps);

        $yearSeconds = (int) (365.25 * 24 * 60 * 60); // 365.25 to include leap years
        $timestampMaxSeconds = \floor(PHP_INT_MAX / 1e9);

        // take median from system clock resolution, better clock, the better performance
        $systemClockMicrotimeMedian = $clockMicrotimeSteps[50];

        $timestepNs = $this->resolverIdConfiguration->timestepNs;

        return [
            'timestampOffset' => $this->timestampOffset,
            'timestampOffsetDate [UTC]' => (new \DateTime('@' . $this->timestampOffset))->format('Y-m-d H:i:s'),
            'approx time left [years]' => ($timestampMaxSeconds - time() + $this->timestampOffset) / $yearSeconds,
            'approx ending date [UTC]' => (new \DateTime('@' . $timestampMaxSeconds + $this->timestampOffset))->format('Y-m-d H:i:s'),
            'workers' => 1 << $this->resolverIdConfiguration->workersBits,
            'groups' => 1 << $this->resolverIdConfiguration->groupsBits,
            'max sequence' => 1 << $this->resolverIdConfiguration->sequenceBits,
            'timestep [ns]' => $timestepNs,
            'system clock step [us] (lower is better, <1 is best)' => $systemClockMicrotimeMedian,
        ];
    }

    public function releaseWorker(): bool
    {
        if ($this->workerId === -1) {
            return false;
        }

        $this->workerId = -1;

        $lastGen = $this->lastIdGenTimeUs + ($this->timestampOffset * 1_000_000);

        try {
            return $this->workerResolver->releaseWorker(
                $lastGen,
                $this->lastNanoTimeStep,
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Evaluate time offset for the generator to match Snowflake id.
     * This can be used to migrate from Snowflake to extend id lifespan without collisions because
     * Snowflake ids grow faster in time (by ~4,2x).
     * Offset must be generated once and used in this class constructor without further change.
     *
     * @param int $startInSeconds Define seconds in future from now where both ids will match. Until that time,
     *                            this generator will return id greater than snowflake.
     */
    public static function getOffsetFromSnowflakeId(int $snowflakeId, int $startInSeconds = 0): int
    {
        $multiplier = ((PHP_INT_MAX / 1e6) / // number of max milliseconds in this generator
            (float) (2 ** 41)); // number of max milliseconds in snowflake;

        $snowflakeSeconds = ($snowflakeId >> 22) / 1e3;

        return time() - (int) ceil(($snowflakeSeconds + $startInSeconds) * $multiplier) + $startInSeconds;
    }

    public function getWorkerIdFromId(int $id): int
    {
        return ($id >> ($this->resolverIdConfiguration->groupsBits + $this->resolverIdConfiguration->sequenceBits)) & ((1 << $this->resolverIdConfiguration->workersBits) - 1);
    }

    public function getSequenceFromId(int $id): int
    {
        return ($id >> $this->resolverIdConfiguration->groupsBits) & ((1 << $this->resolverIdConfiguration->sequenceBits) - 1);
    }

    public function getGroupIdFromId(int $id): int
    {
        return $id & ((1 << $this->resolverIdConfiguration->groupsBits) - 1);
    }

    /**
     * @return int Nanoseconds count from $timestampOffset (assuming when ID was generated it was the same)
     */
    public function getTimestampFromId(int $id): int
    {
        return $id & $this->metadataBitsMask;
    }
}
