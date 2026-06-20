<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId;

use Pvmlibs\FlexId\Contracts\IdGeneratorContract;
use Pvmlibs\FlexId\Contracts\WorkerResolverContract;
use Pvmlibs\FlexId\Contracts\WorkerResolverHasTTLContract;
use Pvmlibs\FlexId\Exceptions\IdConfigurationException;
use Pvmlibs\FlexId\Exceptions\IdGeneratorException;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\VO\IdConfiguration;

/**
 * 64-bit integer ID generator with focus on performance and flexibility.
 * Best to use as singleton for optimal performance.
 * IDs can be generated up to ~292 years from timestampOffset.
 * Use worker resolver that best suit your needs.
 */
class FlexIdGenerator implements IdGeneratorContract
{
    private int $timestepMetaData = 0;
    private int $sequence = 0;
    private int $lastWorkerResolveNanoTimeNs = 0;
    private int $lastNanoTimeStep = 0;
    private int $lastPointInTimeNanoTimeStep = 0;
    private int $fallbackGeneratorUseUntilUs = 0;
    private int $workerId = -1;
    private int $lastWorkerId = -1;
    private int $lastIdGenTimeUs = 0;
    private int $maxMicroseconds;
    private readonly int $timestampOffset;
    private readonly int $metadataBitsMask;
    private readonly int $workerTTLns;
    private readonly bool $workerDependOnTimestep;
    public readonly IdConfiguration $resolverIdConfiguration;

    /**
     * @param ?FlexIdGenerator $fallbackGenerator defines fallback generator, in case of this generator throws NoWorkerAvailableException fallback is used. Can be used in cascade.
     * @param int              $sleepThresholdNs  defines minimum time in nanoseconds to next timestep on sequence overflow when generator will sleep. This is for balance between performance and CPU efficiency.
     *                                            During this time CPU usage will be high but will do job sooner. Smallest available sleep is usually > 50000 ns.
     *
     * @throws \Exception
     */
    public function __construct(
        public readonly WorkerResolverContract $workerResolver,
        private readonly ?FlexIdGenerator $fallbackGenerator = null,
        private int $sleepThresholdNs = 10000,
    ) {
        $this->resolverIdConfiguration = $this->workerResolver->getConfiguration();

        if ($this->resolverIdConfiguration->workersBits === 0 && $this->resolverIdConfiguration->useNewWorkerOnSequenceOverflow) {
            throw new IdConfigurationException('useNewWorkerOnSequenceOverflow needs more workerBits than 0');
        }

        $this->metadataBitsMask = -1 << $this->resolverIdConfiguration->totalMetaDataBits;

        $minimumWorkerTTLms = (int) ceil($this->resolverIdConfiguration->timestepNs / 1e6);
        if ($this->workerResolver instanceof WorkerResolverHasTTLContract) {
            $workerTTLms = $this->workerResolver->getTTLms();
        } else {
            $workerTTLms = \intdiv(PHP_INT_MAX, 1_000_000);
        }

        if ($workerTTLms < $minimumWorkerTTLms) {
            throw new IdConfigurationException("Worker TTL must be at least {$minimumWorkerTTLms} ms to guarantee uniqueness for given parameters");
        }
        // convert to nanoseconds, make sure we have at least 1 timestep margin in ID generation
        $this->workerTTLns = max(1, $workerTTLms - $minimumWorkerTTLms) * 1_000_000 >> $this->resolverIdConfiguration->timestampBitshift;
        $this->workerDependOnTimestep = $this->workerResolver->dependsOnTimestamp();
        $this->timestampOffset = $this->resolverIdConfiguration->timestampOffset;
        $this->sleepThresholdNs = $this->sleepThresholdNs >> $this->resolverIdConfiguration->timestampBitshift;

        // this includes margin for metadata bits and retries to not exceed signed 64 bit range so we don't put ifs everywhere
        $this->maxMicroseconds = \intdiv(PHP_INT_MAX, 1_000) - \intdiv(
            2 * $this->workerResolver->getMaxWorkerResolveTrials() * $this->resolverIdConfiguration->timestepNs,
            1_000,
        );
    }

    public function __destruct()
    {
        $this->releaseWorker();
    }

    /**
     * @throws IdGeneratorException|NoWorkerAvailableException
     */
    public function id(bool $reResolveWorker = false): int
    {
        $nowUs = $this->getTimestampWithOffset();
        $nanoTimeStep = ($nowUs * 1_000) & $this->metadataBitsMask;

        // handle time drift backwards
        if ($this->lastIdGenTimeUs > $nowUs) {
            if ($this->timestampOffset * 1_000_000 > $nowUs) {
                throw new IdGeneratorException('Timestamp offset cannot be future date');
            }
            $diffUs = ($this->lastIdGenTimeUs - $nowUs) << $this->resolverIdConfiguration->timestampBitshift;
            if ($diffUs > 1e5) { // up to 100ms
                throw new IdGeneratorException(\sprintf('Time on server is too unstable, 100 ms diff in last %d ms', $this->workerTTLns / 1e6));
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

        $nowUsWithShift = $nowUs;
        $nanoTimeRef = $nanoTimeStep;

        if ($this->sequence === $this->resolverIdConfiguration->maxSequence) {
            $nextTimestep = $this->lastNanoTimeStep + $this->resolverIdConfiguration->timestepNs;

            while ($nanoTimeStep === $this->lastNanoTimeStep) {
                if ($this->resolverIdConfiguration->useNewWorkerOnSequenceOverflow
                    && $this->workerId !== $this->lastWorkerId) { // when got the same worker after release, then wait to prevent loop
                    // release to prevent getting the same by chance (so we can reset sequence)
                    $this->releaseWorker($nowUs);

                    return $this->id(true);
                }

                // Calculate time to next timestep, for performance it's better to wait a few cycles than to sleep
                // but for CPU usage it's better to sleep for longer periods
                if (($diff = ($nextTimestep - ($nowUsWithShift * 1_000))) > $this->sleepThresholdNs) {
                    \usleep(\intdiv($diff, 1_000) + 1);
                }
                $nowUsWithShift = $this->getTimestampWithOffset();
                $nanoTimeStep = ($nowUsWithShift * 1_000) & $this->metadataBitsMask;
            }
        }

        if ($nanoTimeStep !== $this->lastNanoTimeStep) {
            $this->sequence = 0;
            if ($nanoTimeStep - $this->lastWorkerResolveNanoTimeNs >= $this->workerTTLns) {
                // increment only 1 timestep if we exceeded timeout when waiting for worker
                // generator timeout is at least 1 timestep lower than worker so we will be still within timeout
                $nanoTimeStep = $nanoTimeRef + $this->resolverIdConfiguration->timestepNs;
                // make sure to update also nowUsWithShift as we use it for lastIdGenTimeNs
                $nowUsWithShift = $this->getTimestampWithOffset();
            }
        }

        // if resolver depends on timestamp to provide worker, and if timestamp has changed since the time of resolving worker
        // we need to reresolve worker for new timestep
        if (($this->lastWorkerResolveNanoTimeNs !== $nanoTimeStep) && $this->workerDependOnTimestep) {
            return $this->id(true);
        }

        $this->lastNanoTimeStep = $nanoTimeStep;
        $this->lastIdGenTimeUs = $nowUsWithShift;

        return $nanoTimeStep | $this->timestepMetaData | ($this->sequence++ << $this->resolverIdConfiguration->groupsBits);
    }

    /**
     * This method can be used when backfilling id in arbitrary point of time - unix microseconds.
     * It is extracted from id() as it should not interfere with workers so you can use it along with id() but
     * it is not guaranteed that idInTime() will produce unique id when used within same timestamp as in id().
     * There is also limit up to max sequence within same $referenceMicroTimestamp, exceeding it will generate exception
     * It can also produce duplicates when skipping back and forth between same timestamps, so use them ordered.
     *
     * @throws IdGeneratorException
     */
    public function idInTime(int $referenceMicroTimestamp = 0, int $workerId = 0): int
    {
        $microtime = \intval($referenceMicroTimestamp - $this->timestampOffset * 1_000_000);
        $microtime >>= $this->resolverIdConfiguration->timestampBitshift;

        if ($microtime >= $this->maxMicroseconds || $microtime < 0) {
            $range = $this->getTimeRangeYears();
            throw new IdGeneratorException(\sprintf('Time range is exhausted for this configuration. Supported range is %s - %s', $this->getTimestampOffsetDate(), (new \DateTime('@' . (time() + $range * 365.25 * 24 * 60 * 60)))->format('Y-m-d H:i:s')));
        }
        $nanoTimeStep = ($microtime * 1_000) & $this->metadataBitsMask;

        if ($nanoTimeStep !== $this->lastPointInTimeNanoTimeStep) {
            $this->sequence = 0;
            $this->lastPointInTimeNanoTimeStep = $nanoTimeStep;
        }

        if ($this->sequence === $this->resolverIdConfiguration->maxSequence) {
            throw new IdGeneratorException(\sprintf('Reference time is set and sequence of max %d is exhausted, make sure generator can produce such id range within the same timestamp', $this->sequence));
        }

        if ($workerId >= $this->resolverIdConfiguration->maxWorkers || $workerId < 0) {
            throw new IdGeneratorException(\sprintf('Worker id %d is out of range 0-%d', $workerId, $this->resolverIdConfiguration->maxWorkers - 1));
        }

        $groupAndSequenceBits = $this->resolverIdConfiguration->groupsBits + $this->resolverIdConfiguration->sequenceBits;
        $id = $nanoTimeStep | (($workerId << $groupAndSequenceBits) | $this->resolverIdConfiguration->groupId) | ($this->sequence++ << $this->resolverIdConfiguration->groupsBits);

        return $id;
    }

    public function getTimestampWithOffset(): int
    {
        $microtime = (int) ((\microtime(true) - $this->timestampOffset) * 1_000_000);
        $microtime >>= $this->resolverIdConfiguration->timestampBitshift;

        if ($microtime >= $this->maxMicroseconds || $microtime < 0) {
            $range = $this->getTimeRangeYears();
            throw new IdGeneratorException(\sprintf('Time range is exhausted for this configuration. Supported range is %s - %s', $this->getTimestampOffsetDate(), (new \DateTime('@' . (time() + $range * 365.25 * 24 * 60 * 60)))->format('Y-m-d H:i:s')));
        }

        return $microtime;
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
     * @throws NoWorkerAvailableException|IdGeneratorException
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
                    $nextTimestepUs = (int) ceil(($nanoTimeStep + $this->resolverIdConfiguration->timestepNs) / 1_000);
                    if ($nextTimestepUs < 0) {
                        throw new IdGeneratorException('Time range is exhausted for this configuration');
                    }
                    $lockTimeUs = max(1, $nextTimestepUs - $nowUs);
                } else {
                    $lockTimeUs = $exception->lockTimeUs;
                }

                if ($tries < $this->workerResolver->getMaxWorkerResolveTrials()) {
                    // handle extreme cases
                    if ($lockTimeUs < 0 || $lockTimeUs > 4_000_000) {
                        throw new IdGeneratorException('Incorrect lock timeout ' . $lockTimeUs);
                    }

                    \usleep($lockTimeUs);

                    $nowUs = $this->getTimestampWithOffset();
                    $nanoTimeStep = ($nowUs * 1_000) & $this->metadataBitsMask;

                    continue;
                }
            }
            break;
        }

        if ($workerId === null && $exception !== null) {
            throw new NoWorkerAvailableException($exception->maxWorkers, $exception->groupId, $lockTimeUs);
        }

        if ($workerId >= $this->resolverIdConfiguration->maxWorkers || $workerId < 0 || $workerId === null) {
            throw new IdGeneratorException(\sprintf('Worker resolver return id %d out of range 0-%d', $workerId, $this->resolverIdConfiguration->maxWorkers - 1));
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
     * Use in comparison (e.g. in DB scans) only with IDs generated with the same timestampOffset and timestampBitshift.
     * To be comparable between IDs on millisecond level, the sum of id metadata bits should be < 19 (timestep 0,262ms).
     * If there are more bits, timestamp precision will be less precise.
     * If you use different ID metadata bits configurations, use one with the largest bits count.
     *
     * @throws \Exception
     */
    public function toDate(int $id): \DateTime
    {
        if ($id < 0) {
            throw new \InvalidArgumentException('Id must be greater than 0');
        }

        // clear metadata bits
        $timestamp = $this->getTimestampFromId($id);
        $timestamp /= 1e6;
        $seconds = \floor($timestamp);
        $microseconds = $timestamp - $seconds;

        $seconds += $this->resolverIdConfiguration->timestampOffset;

        $microseconds = \number_format($microseconds, 3); // 1 ms precision

        $date = \DateTime::createFromFormat('U 0.u', "{$seconds} {$microseconds}", new \DateTimeZone('UTC'));

        if ($date === false) {
            throw new IdGeneratorException('Unable to parse date from id ' . $id);
        }

        return $date;
    }

    /**
     * Use in comparison only with IDs generated with the same timestampOffset and timestampBitshift.
     */
    public function fromDate(\DateTime $date): int
    {
        $microseconds = $date->setTimezone(new \DateTimeZone('UTC'))->format('Uu');

        $timestamp = $microseconds - $this->resolverIdConfiguration->timestampOffset * 1_000_000;
        $timestamp = $timestamp >> $this->resolverIdConfiguration->timestampBitshift;
        $timestamp *= 1_000;

        if ($timestamp < 0 || \is_float($timestamp)) {
            throw new \InvalidArgumentException("Date id beyond generator time offset ({$this->getTimestampOffsetDate()})");
        }

        // pad to nanoseconds
        return $timestamp & $this->metadataBitsMask;
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
        $clockHrtimeSteps = [];

        $gatherClockDataFn(fn () => \microtime(true) * 1_000_000, $clockMicrotimeSteps);
        $gatherClockDataFn(fn () => \hrtime(true), $clockHrtimeSteps);

        $timestepNs = $this->resolverIdConfiguration->timestepNs;

        // take median from system clock resolution, better clock, the better performance
        $systemClockMicrotimeMedian = $clockMicrotimeSteps[50];
        $systemClockHrtimeMedian = $clockHrtimeSteps[50];

        $range = $this->getTimeRangeYears();

        $sleepStart = \hrtime(true);
        \usleep(1);
        $sleepEnd = \hrtime(true);

        return [
            'timestampOffset' => $this->resolverIdConfiguration->timestampOffset,
            'timestampOffsetDate [UTC]' => $this->getTimestampOffsetDate(),
            'approx time left [years]' => \round($range, 3),
            'approx ending date [UTC]' => (new \DateTime('@' . (time() + $range * 365.25 * 24 * 60 * 60)))->format('Y-m-d H:i:s'),
            'worker bits' => $this->resolverIdConfiguration->workersBits,
            'sequence bits' => $this->resolverIdConfiguration->sequenceBits,
            'group bits' => $this->resolverIdConfiguration->groupsBits,
            'timestamp bitshift' => $this->resolverIdConfiguration->timestampBitshift,
            'timestep [ns]' => $timestepNs,
            'system clock step [us]' => $systemClockMicrotimeMedian,
            'system resolution [ns]' => $systemClockHrtimeMedian,
            'min usleep time [us]' => ceil(($sleepEnd - $sleepStart) / 1e3),
        ];
    }

    public function getTimeRangeYears(): float
    {
        $yearMicroSeconds = 1_000_000 * (int) (365.25 * 24 * 60 * 60); // 365.25 to include leap years

        $rangeMultiplier = \min(1_000, 1 << \min($this->resolverIdConfiguration->timestampBitshift, 10));

        return $rangeMultiplier * ($this->maxMicroseconds - 1_000_000 * (\time() - $this->timestampOffset)) / $yearMicroSeconds;
    }

    public function releaseWorker(int $nowUs = 0): bool
    {
        if ($this->workerId === -1) {
            return false;
        }

        $this->workerId = -1;

        try {
            return $this->workerResolver->releaseWorker(
                $this->lastIdGenTimeUs,
                $this->lastNanoTimeStep,
                $nowUs,
            );
        } catch (\Throwable $e) {
            return false;
        }
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
     * Microseconds timestamp part from id.
     */
    public function getTimestampFromId(int $id): int
    {
        if ($id < 0) {
            throw new \InvalidArgumentException('Id must be greater than 0');
        }

        $timestamp = \intdiv($id & $this->metadataBitsMask, 1_000) << $this->resolverIdConfiguration->timestampBitshift;

        if ($timestamp < 0) {
            throw new \InvalidArgumentException('Id out of range');
        }

        return $timestamp;
    }

    private function getTimestampOffsetDate(): string
    {
        return (new \DateTime('@' . $this->resolverIdConfiguration->timestampOffset))->format('Y-m-d H:i:s');
    }
}
