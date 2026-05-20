<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Resolvers;

use Predis\Client as PredisClient;

/**
 * This is provided as preconfigured resolver for shorter ids.
 */
class ShortRedisReservedWorkerResolver extends RedisReservedWorkerResolver
{
    public function __construct(
        \Redis|PredisClient $client,
        int $groupId = 0,
        int $workersBits = 2,
        int $sequenceBits = 10,
        int $groupsBits = 0,
        bool $useNewWorkerOnSequenceOverflow = true,
        int $TTLMs = 3000,
        int $minimalWorkerSeparationMs = 0,
        int $timestampOffset = 1735689600,
        int $resolveWorkerTrials = 2,
        int $timestampBitshift = 16, // ~16k id/s, 268ms timestep
    ) {
        parent::__construct(
            client: $client,
            groupId: $groupId,
            workersBits: $workersBits,
            sequenceBits: $sequenceBits,
            groupsBits: $groupsBits,
            useNewWorkerOnSequenceOverflow: $useNewWorkerOnSequenceOverflow,
            TTLMs: $TTLMs,
            minimalWorkerSeparationMs: $minimalWorkerSeparationMs,
            resolveWorkerTrials: $resolveWorkerTrials,
            timestampOffset: $timestampOffset,
            timestampBitshift: $timestampBitshift,
        );
    }
}
