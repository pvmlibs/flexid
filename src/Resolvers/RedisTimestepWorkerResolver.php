<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Resolvers;

use Predis\Client as PredisClient;
use Predis\Connection\ConnectionException;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\VO\IdConfiguration;

/**
 * Resolves worker id using Redis/Valkey database. Requires working NTP service on servers.
 * Can handle large number of generators simultaneously, uses very low Redis memory (with default settings up to 256 B)
 * and moderate Redis utilization - one generator will send one request/268 ms/up to max sequence (with default settings).
 * Memory usage within $timestepExpireSec is proportional to ID generation rate and lower metadata bits
 * (so lower timestep, DB entry is per timestep). For example, using $workersBits 18 and $sequenceBits 10 gives
 * ~268ms timestep (maximum allowed), with $timestepExpireSec=4s, 4/0.268 * 16 B (per timestep) = max usage 256 B.
 * With defaults settings there is maximum throughput of 2^18 * (1/0.268) = 978149 workers requests/sec and each worker
 * can then use offline (without Redis communication) sequence of max 256 (2^8) within timestep (268 ms).
 * Redis/Valkey DB throughput will be much lower, like <100k requests/s so if your application needs more, you will need
 * separate databases, adjust $groupsBits for number of databases and use $groupId in e.g. round-robin or random way.
 * Can be used in large number of short-lived processes.
 */
class RedisTimestepWorkerResolver implements WorkerResolverContract
{
    protected int $workerId = -1;
    protected string $workersKey;

    /**
     * @var \Closure(string, int, array<int|string>): mixed
     */
    protected \Closure $redisEval;
    protected string $prefix = '_flexid_tw:';
    protected IdConfiguration $configuration;

    /**
     * @param \Redis|PredisClient $client                         Redis client, for best performance Redis is preferred (ext-redis)
     * @param int                 $resolveWorkerTrials            How many tries to allow if there are no worker/database available. With no worker available (reached max workers in given timestep),
     *                                                            each try will be separated by up to timestep time.
     * @param int                 $timestepExpireSec              time in seconds after timestep entry with last worker id will expire and be removed.
     *                                                            This time resolves problems with clock differences between hosts and synchronizes workers.
     * @param bool                $useNewWorkerOnSequenceOverflow use new worker on sequence overflow within given timestep. By default, it allows for better performance in burst ID generation.
     *
     * @throws \Exception
     */
    public function __construct(
        private \Redis|PredisClient $client,
        public readonly int $groupId = 0,
        public readonly int $workersBits = 18,
        public readonly int $sequenceBits = 10,
        public readonly int $groupsBits = 0,
        public readonly bool $useNewWorkerOnSequenceOverflow = true,
        public readonly int $resolveWorkerTrials = 2,
        public readonly int $timestepExpireSec = 4,
        public readonly int $timestampBitshift = 0,
        public readonly int $timestampOffset = 1735689600, // UTC 2025-01-01
    ) {
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
            timestampBitshift: $this->timestampBitshift,
            timestampOffset: $this->timestampOffset,
        );

        $this->setPrefix($this->prefix);
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
        /* @var false|int $result */
        try {
            $result = ($this->redisEval)(
                <<<'LUA'
                    if redis.call('set', KEYS[1], 0, "EX", ARGV[1], "NX") then
                        return 0
                    else
                        return redis.call('incr', KEYS[1])
                    end
                    LUA,
                1,
                [
                    $this->workersKey . $currentTimestepNs,   // KEYS1
                    $this->timestepExpireSec,        // ARGV1
                ]
            );
        } catch (\RedisException|ConnectionException $exception) {
            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId, previous: $exception);
        }

        if ($result === false) {
            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId, previous: new \Exception('Error in worker script'));
        }

        if ($result >= $this->configuration->maxWorkers) {
            throw new NoWorkerAvailableException(maxWorkers: $this->configuration->maxWorkers, groupId: $this->groupId);
        }

        $this->workerId = $result;

        return $this->workerId;
    }

    protected function setPrefix(string $prefix): self
    {
        // concurrent workers with different time configurations should have separate keys
        $this->prefix = $prefix . "{$this->timestampBitshift}:{$this->timestampOffset}:";
        $this->workersKey = $this->prefix . 'ts_workers:' . $this->groupId . ':';

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function getDBUsage(): int
    {
        $result = ($this->redisEval)(<<<'LUA'
                    local cursor = 0
                    local i = 0
                    local size = 0

                    repeat
                        local result = redis.call("scan", cursor, "MATCH", KEYS[1], "COUNT", 100)
                        cursor = result[1]

                        for i = 1, #result[2], 1 do
                            size = size + tonumber(redis.call("memory", "usage", result[2][i], "samples", 5))
                        end
                    until cursor == "0"
                    return size
            LUA, 1, [
            $this->workersKey . '*',
        ]);

        if ($result !== false) {
            return $result;
        }
        throw new \Exception('Cannot get size info');
    }

    public function clearDatabase(): void
    {
        ($this->redisEval)(
            <<<'LUA'
                    redis.call("del", KEYS[1]);
                LUA,
            1,
            [
                $this->workersKey,
            ]
        );
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
}
