<?php

namespace Pvmlibs\FlexId\Resolvers;

use Predis\Client as PredisClient;
use Predis\Connection\ConnectionException;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\VO\IdConfiguration;

/**
 * Resolves worker id using Redis/Valkey database by reserving worker from pool for given $TTLMs time.
 * Requires working NTP service on servers.
 * Uses low to moderate Redis memory (with default settings up to 49 kB) and low Redis utilization - one generator can
 * send max 1 request in 10 s (with default settings). Not suited for large number of independent generators (like 100k).
 * Redis memory usage will grow linear with $workersBits, e.g. 10bits ~49kB, 16bit ~3,1MB, 20bit ~50MB
 * and stays on same level all time. When changing $workersBits from higher to lower, memory will not be freed automatically,
 * you need to perform removeExpiredWorkers() or clearDatabase() manually.
 * This resolver is ~40% more Redis CPU expensive within single worker than RedisTimestepWorkerResolver request and more
 * memory expensive - depending on $workersBits than RedisTimestepWorkerResolver but can be more effective for longer processes.
 * Set maxWorkers as 2x number of running generator processes to have some margins, so e.g. with concurrent 500 processes
 * set $workersBits 10.
 */
class RedisReservedWorkerResolver implements WorkerResolverContract, WorkerResolverHasTTLContract
{
    private int $lastTimeoutUs = -1;
    private int $workerId = -1;

    private string $workersKey;
    private string $nextWorkerIdKey;
    private string $lockKey;

    /**
     * @var \Closure(string, int, array<int|string>): mixed
     */
    private \Closure $redisEval;

    private int $separationMs = 0;

    /**
     * Redis/Valkey uses Lua 5.1 which does not fully support int 64bit.
     * Numbers are handled as doubles and int precision can be retained to 2 ** 53 (similar to JS).
     * So we offset by this number, which gives us range ~ -285 to +285 years, so ~570 years from $timestampOffsetUs.
     */
    private const LUA_INT_OFFSET = 9007199254740992; // 2 ** 53

    private string $prefix = '_flexid_rw:';

    private readonly IdConfiguration $configuration;

    /**
     * @param \Redis|PredisClient $client                    Redis client, for best performance Redis is preferred (ext-redis)
     * @param int                 $TTLMs                     Worker slot timeout in ms. Slot is automatically released on instance destroy
     *                                                       but keep in mind, that due to abnormal script termination like OOM event, the slot won't be released.
     *                                                       So this time should be kept reasonably low, be default it's 10s.
     * @param int                 $minimalWorkerSeparationMs Time separation between a worker slot can be reassigned after timeout.
     *                                                       It's 'at least' time, it can be larger depending on $TTLMs and timestep (see $this->separationMs).
     *                                                       The same worker will try to use the same id to improve pool availability.
     *                                                       Higher value will lower theoretical throughput of worker reservations per second.
     * @param int                 $resolveWorkerTrials       How many tries to allow if there are no worker/database available. With no worker available, next try will wait until lowest TTL slot.
     * @param int                 $timestampOffsetUs         timestamp offset used to extend Redis int number handling, this is used in addition to LUA_INT_OFFSET but can be set by user
     *                                                       Once set, don't change it later, or you will need to perform clearDatabase()
     *
     * @throws \Exception
     */
    public function __construct(
        private \Redis|PredisClient $client,
        public readonly int $groupId = 0,
        public readonly int $workersBits = 10,
        public readonly int $sequenceBits = 8,
        public readonly int $groupsBits = 0,
        public readonly bool $useNewWorkerOnSequenceOverflow = false,
        public readonly int $TTLMs = 10000,
        public readonly int $minimalWorkerSeparationMs = 50,
        public readonly int $resolveWorkerTrials = 1,
        public readonly int $timestampOffsetUs = 1735689600000000, // UTC 2025-01-01
    ) {
        if ($this->timestampOffsetUs < 0) {
            throw new \Exception('Timestamp offset must be >= 0');
        }

        $this->redisEval = match ($client::class) {
            \Redis::class => fn (string $script, int $keysNum, array $argsAndKeys) => $this->client->eval($script, $argsAndKeys, $keysNum),
            PredisClient::class => fn (string $script, int $keysNum, array $argsAndKeys) => $this->client->eval(
                $script,
                $keysNum,
                ...$argsAndKeys, // @phpstan-ignore argument.named
            ),
            default => throw new \Exception('Client not supported'),
        };

        $this->configuration = new IdConfiguration(
            workersBits: $this->workersBits,
            sequenceBits: $this->sequenceBits,
            groupsBits: $this->groupsBits,
            groupId: $this->groupId,
            useNewWorkerOnSequenceOverflow: $this->useNewWorkerOnSequenceOverflow,
        );

        $this->setPrefix($this->prefix);

        $timeStepMs = (int) \ceil($this->configuration->timestepNs / 1e6);

        $this->separationMs = \max(
            $this->minimalWorkerSeparationMs,
            (int) \ceil($this->TTLMs * 0.005),
            2 * $timeStepMs,
        );
    }

    public function getTTLms(): int
    {
        return $this->TTLMs;
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
        $currentTimeUs -= ($this->timestampOffsetUs + self::LUA_INT_OFFSET);
        $newTimeoutUs = ($currentTimeUs + (1_000 * $this->TTLMs));

        if ($currentTimeUs < -self::LUA_INT_OFFSET) {
            throw new \Exception('Redis timestamp offset must be represent past UNIX date in microseconds');
        }

        /* @var false|list<int> $result */
        try {
            $result = ($this->redisEval)(<<<'LUA'
                    local nextWorkerIdKey = KEYS[1]
                    local workerSlotsKey = KEYS[2]
                    local lockKey = KEYS[3]
                    local newWorkerTimeoutUs = tonumber(ARGV[3])
                    local lowestTimestampUs = tonumber(ARGV[5])
                    local currentWorkerId = tonumber(ARGV[6])
                    local currentTimeoutUs = tonumber(ARGV[7])
                    local workerTimeoutUs
                    local trials = 1
                    
                    -- try to reuse current worker
                    if currentWorkerId ~= -1 then
                        workerTimeoutUs = tonumber(redis.call('hget', workerSlotsKey, currentWorkerId) or lowestTimestampUs)
                                    
                        -- check if we still hold worker
                        if currentTimeoutUs == workerTimeoutUs then
                            redis.call('hset', workerSlotsKey, currentWorkerId, newWorkerTimeoutUs)
                            return {currentWorkerId, trials}
                        end
                    end
                    
                    local currentTimeUs = tonumber(ARGV[2])     
                    local lockUntilUs = tonumber(redis.call('get', lockKey) or lowestTimestampUs)
                    
                    -- prevent excessive worker search if all are busy
                    if lockUntilUs > currentTimeUs then
                        return {-1, lockUntilUs - currentTimeUs}
                    end
                    
                    local maxWorkers = tonumber(ARGV[1])
                    local workerSeparationUs = tonumber(ARGV[4])
                    local minTimeout = -lowestTimestampUs

                    -- of no worker available in queue take next one from current pointer
                    local workerId = tonumber(redis.call('get', nextWorkerIdKey) or lowestTimestampUs) % maxWorkers
                    local minTimeoutWorker = workerId
                    
                    while trials <= maxWorkers do
                        workerTimeoutUs = tonumber(redis.call('hget', workerSlotsKey, workerId) or lowestTimestampUs)
                            
                        if currentTimeUs > workerTimeoutUs + workerSeparationUs then
                            redis.call('hset', workerSlotsKey, workerId, newWorkerTimeoutUs)
                            redis.call('set', nextWorkerIdKey, workerId + 1)
                            return {workerId, trials}
                        end
                        
                        -- save lowest timeout from trials
                        if workerTimeoutUs < minTimeout then
                            minTimeoutWorker = workerId
                            minTimeout = workerTimeoutUs
                        end

                        workerId = (workerId + 1) % maxWorkers
                        trials = trials + 1
                    end
                    
                    redis.call('set', lockKey, minTimeout + workerSeparationUs + 1)
                    redis.call('set', nextWorkerIdKey, minTimeoutWorker)
                    return {-1, workerTimeoutUs + workerSeparationUs - currentTimeUs}
                LUA, 3, [
                $this->nextWorkerIdKey, // KEYS1
                $this->workersKey,      // KEYS2
                $this->lockKey,         // KEYS3
                $this->configuration->maxWorkers,      // ARGV1
                $currentTimeUs,         // ARGV2
                $newTimeoutUs,          // ARGV3
                $this->separationMs * 1_000,    // ARGV4
                -self::LUA_INT_OFFSET,          // ARGV5
                $this->workerId,      // ARGV6
                $this->lastTimeoutUs, // ARGV7
            ]);

        } catch (\RedisException|ConnectionException $exception) {
            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId, previous: $exception);
        }

        if ($result === false) {
            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId, previous: new \Exception('Error in worker script'));
        }

        if ((int) $result[0] === -1) {
            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId, lockTimeUs: $result[1]);
        }

        $this->lastTimeoutUs = $newTimeoutUs;
        $this->workerId = $result[0];

        return $this->workerId;
    }

    public function releaseWorker(int $lastIdGenTimeUs = 0, int $lastTimeStepNs = 0): bool
    {
        if ($this->workerId === -1) {
            return false;
        }

        $nowUs = ((int) (\microtime(true) * 1_000_000)) - ($this->timestampOffsetUs + self::LUA_INT_OFFSET);

        // when worker TTL has expired don't release it in DB
        if ($nowUs >= $this->lastTimeoutUs) {
            $this->workerId = $this->lastTimeoutUs = -1;

            return false;
        }

        if ($lastIdGenTimeUs !== 0) {
            $newTimeoutUs = $lastIdGenTimeUs - ($this->timestampOffsetUs + self::LUA_INT_OFFSET);
        } else {
            $newTimeoutUs = $nowUs;
        }

        $result = ($this->redisEval)(
            <<<'LUA'
                    local workerId = ARGV[3]
                    local lastWorkerStartTimeoutUs = ARGV[1]
                    local newWorkerTimeout = ARGV[2]
                    
                    -- need to verify we still hold this worker
                    if redis.call('hget', KEYS[1], workerId) == lastWorkerStartTimeoutUs then
                        return redis.call('hset', KEYS[1], workerId, newWorkerTimeout)
                    else
                        return false
                    end
                LUA,
            1,
            [
                $this->workersKey,       // KEYS1
                $this->lastTimeoutUs,    // ARGV1
                $newTimeoutUs,           // ARGV2
                $this->workerId,         // ARGV3
            ]
        );

        $this->workerId = $this->lastTimeoutUs = -1;

        return $result !== false;
    }

    /**
     * Returns number of currently used slots, can be used periodically to monitor how much slots is taken.
     *
     * @param int $timeOffsetMs Time offset to worker timeout (+ separation). Negative values go back in time,
     *                          so e.g. value -10 means count also 10ms after slot timed off (with separation) .
     *
     * @return array{usedWorkers: int, nextWorkerId: int, lastLockUs: int|null, lastLockDate:\DateTime|null}
     *
     * @throws \Exception
     */
    public function getCurrentlyUsedWorkers(int $timeOffsetMs = 0): array
    {
        $now = ((int) (\microtime(true) * 1_000_000)) - ($this->timestampOffsetUs + self::LUA_INT_OFFSET);

        /** @var false|list<int|false|null> $slotsUsedNum */
        $slotsUsedNum = ($this->redisEval)(
            <<<'LUA'
                    local cursor = 0
                    local count = 0
                    local i = 0
                    local timestampRefUs = tonumber(ARGV[1])
                    local timeOffsetUs = tonumber(ARGV[2])
                    local lockKey = KEYS[2]
                    local nextWorkerKey = KEYS[3]
                    
                    repeat
                        local result = redis.call("hscan", KEYS[1], cursor, "COUNT", 500)
                        cursor = result[1]

                        for i = 2, #result[2], 2 do
                            local workerTimeoutUs = tonumber(result[2][i])
                            if workerTimeoutUs >= (timestampRefUs + timeOffsetUs) then
                                count = count + 1
                            end                           
                        end
                    until cursor == "0"
                    
                    return {count, redis.call('get', lockKey), redis.call('get', nextWorkerKey) or 0}
                LUA,
            3,
            [
                $this->workersKey,    // KEYS1
                $this->lockKey,       // KEYS2
                $this->nextWorkerIdKey,   // KEYS3
                $now - (1_000 * $this->separationMs), // ARGV1
                1_000 * $timeOffsetMs,   // ARGV2
            ]
        );

        if ($slotsUsedNum === false) {
            throw new \Exception('Cannot get number of used slots');
        }

        if ($slotsUsedNum[1] !== null && $slotsUsedNum[1] !== false) {
            $lockDate = $slotsUsedNum[1] + self::LUA_INT_OFFSET + $this->timestampOffsetUs;
            $lockDateSeconds = $lockDate / 1e6;
            $lockDateSecondsInt = \floor($lockDateSeconds);
            $lockDateMicroseconds = round($lockDateSeconds - $lockDateSecondsInt, 6);
            $lockDateTime = \DateTime::createFromFormat('U 0.u', "{$lockDateSecondsInt} {$lockDateMicroseconds}", new \DateTimeZone('UTC'));
        } else {
            $lockDate = $lockDateTime = null;
        }

        return [
            'usedWorkers' => (int) $slotsUsedNum[0],
            'nextWorkerId' => (int) $slotsUsedNum[2],
            'lastLockUs' => $lockDate,
            'lastLockDate' => $lockDateTime === false ? null : $lockDateTime,
        ];
    }

    private function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
        $this->workersKey = $this->prefix . 'workers:' . $this->groupId . ':';
        $this->nextWorkerIdKey = $this->prefix . 'next_id:' . $this->groupId . ':';
        $this->lockKey = $this->prefix . 'lock:' . $this->groupId . ':';
    }

    /**
     * @return array{workersSizeBytes: int, workersSlotsWritten: int}
     *
     * @throws \Exception
     */
    public function getDBUsage(): array
    {
        $result = ($this->redisEval)(<<<'LUA'
                return {
                        redis.call("memory", "usage", KEYS[1], "samples", 5),
                        redis.call("hlen", KEYS[1]),
                }
            LUA, 1, [
            $this->workersKey,
        ]);

        if ($result !== false) {
            return [
                'workersSizeBytes' => $result[0],
                'workersSlotsWritten' => $result[1],
            ];
        }
        throw new \Exception('Cannot get size info');
    }

    /**
     * Perform when need to reclaim DB memory after changing workerBits to smaller value.
     */
    public function removeExpiredWorkers(): void
    {
        $now = ((int) (\microtime(true) * 1_000_000)) - ($this->timestampOffsetUs + self::LUA_INT_OFFSET);

        ($this->redisEval)(
            <<<'LUA'
                    local cursor = 0
                    local count = 0
                    local i = 0
                    local timestampRefUs = tonumber(ARGV[1])
                    
                    repeat
                        local result = redis.call("hscan", KEYS[1], cursor, "COUNT", 500)
                        cursor = result[1]

                        for i = 2, #result[2], 2 do
                            local workerTimeoutUs = tonumber(result[2][i])
                            if workerTimeoutUs < timestampRefUs then
                                redis.call('hdel', KEYS[1], result[2][i-1])
                            end                                
                        end
                    until cursor == "0"
                LUA,
            1,
            [
                $this->workersKey,
                $now - (1_000 * $this->separationMs), // ARGV1
            ]
        );
    }

    public function clearDatabase(): void
    {
        ($this->redisEval)(
            <<<'LUA'
                    redis.call("del", KEYS[1], KEYS[2], KEYS[3]);
                LUA,
            3,
            [
                $this->workersKey,
                $this->nextWorkerIdKey,
                $this->lockKey,
            ]
        );
    }

    public function getMaxWorkerResolveTrials(): int
    {
        return $this->resolveWorkerTrials;
    }

    public function dependsOnTimestamp(): bool
    {
        return false;
    }

    public function getConfiguration(): IdConfiguration
    {
        return $this->configuration;
    }

    public function getWorkerSeparationMs(): int
    {
        return $this->separationMs;
    }
}
