<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Resolvers;

use Predis\Client as PredisClient;

/**
 * This is provided as preconfigured resolver for shorter ids.
 */
class ShortRedisTimestepWorkerResolver extends RedisTimestepWorkerResolver
{
    public function __construct(
        \Redis|PredisClient $client,
        int $groupId = 0,
        int $workersBits = 6,
        int $sequenceBits = 6,
        int $groupsBits = 0,
        bool $useNewWorkerOnSequenceOverflow = true,
        int $resolveWorkerTrials = 4,
        int $timestepExpireSec = 4,
        int $timestampBitshift = 16, // ~16k id/s, 268ms timestep
    ) {
        parent::__construct(
            client: $client,
            groupId: $groupId,
            workersBits: $workersBits,
            sequenceBits: $sequenceBits,
            groupsBits: $groupsBits,
            useNewWorkerOnSequenceOverflow: $useNewWorkerOnSequenceOverflow,
            resolveWorkerTrials: $resolveWorkerTrials,
            timestepExpireSec: $timestepExpireSec,
            timestampBitshift: $timestampBitshift,
        );
    }
}
