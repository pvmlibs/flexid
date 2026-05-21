<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\VO;

use Pvmlibs\FlexId\Exceptions\IdConfigurationException;

class IdConfiguration
{
    public readonly int $totalMetaDataBits;
    public readonly int $timestepNs;
    public readonly int $maxWorkers;
    public readonly int $maxSequence;
    public readonly int $maxGroups;
    public const MAX_METADATA_BITS = 30; // 1073741824 ns -> ~ 1,07 s time resolution with 30 bits

    /**
     * These 2 parameters should be set once to prevent potential id collision:
     *
     * @param int $timestampBitshift how many bits shift timestamp (with microseconds) to right. More bits, lower id and lower throughput
     * @param int $timestampOffset   offset timestamp in seconds, this helps longer id time range
     */
    public function __construct(
        public readonly int $workersBits,
        public readonly int $sequenceBits,
        public readonly int $groupsBits,
        public readonly int $groupId,
        public readonly bool $useNewWorkerOnSequenceOverflow,
        public readonly ?string $lockFilePath = null,
        public readonly int $timestampBitshift = 0,
        public readonly int $timestampOffset = 1735689600, // UTC 2025-01-01
    ) {
        if ($this->timestampOffset < 0) {
            throw new IdConfigurationException('Timestamp offset must be >= 0');
        }

        if (($this->workersBits | $this->groupsBits | $this->sequenceBits) < 0) {
            throw new IdConfigurationException('All bits must be >= 0');
        }

        if ($this->workersBits > 20) {
            throw new IdConfigurationException('Workers bits must be between <1,20>, got ' . $this->workersBits);
        }

        if ($this->groupId < 0 || $this->groupId >= (1 << $this->groupsBits)) {
            throw new IdConfigurationException('Wrong group number, must be < ' . (1 << $this->groupsBits) . ', got ' . $this->groupId);
        }

        if ($this->timestampBitshift > 20 || $this->timestampBitshift < 0) {
            // more than 20 will not make sense with system timer resolution 1us will not take effect - use to rest 10 bits
            // for workers, sequence or groups
            throw new IdConfigurationException('Wrong timestampBitshift, must be <= 20');
        }

        $this->totalMetaDataBits = $this->workersBits + $this->groupsBits + $this->sequenceBits;
        // time step is ~262ms with 28 bits
        if (($this->totalMetaDataBits + $this->timestampBitshift) > self::MAX_METADATA_BITS) {
            throw new IdConfigurationException('Total metadata bits must be <= ' . self::MAX_METADATA_BITS);
        }

        // we can process sequence per worker within this chunk of time
        // choose smallest timestep, microtime has 1us resolution so minimum is 10 bits (1024ns)
        $this->timestepNs = max(1 << $this->totalMetaDataBits, 1 << ($this->timestampBitshift + max($this->totalMetaDataBits, 10)));
        $this->maxWorkers = 1 << $this->workersBits;
        $this->maxSequence = 1 << $this->sequenceBits;
        $this->maxGroups = 1 << $this->groupsBits;
    }
}
