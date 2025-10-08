<?php

namespace Pvmlibs\FlexId\VO;

use Pvmlibs\FlexId\Exceptions\IdConfigurationException;

class IdConfiguration
{
    public readonly int $totalMetaDataBits;
    public readonly int $timestepNs;
    public readonly int $maxWorkers;
    public readonly int $maxSequence;
    public const MAX_METADATA_BITS = 28;

    public function __construct(
        public readonly int $workersBits,
        public readonly int $sequenceBits,
        public readonly int $groupsBits,
        public readonly int $groupId,
        public readonly bool $useNewWorkerOnSequenceOverflow,
        public readonly ?string $lockFilePath = null,
    ) {
        if (($this->workersBits | $this->groupsBits | $this->sequenceBits) < 0) {
            throw new IdConfigurationException('All bits must be >= 0');
        }

        if ($this->workersBits > 20) {
            throw new IdConfigurationException('Workers bits must be between <1,20>, got ' . $this->workersBits);
        }

        if ($this->groupId < 0 || $this->groupId >= (1 << $this->groupsBits)) {
            throw new IdConfigurationException('Wrong group number, must be < ' . (1 << $this->groupsBits) . ', got ' . $this->groupId);
        }

        $this->totalMetaDataBits = $this->workersBits + $this->groupsBits + $this->sequenceBits;
        // time step is ~262ms with 28 bits
        if ($this->totalMetaDataBits > self::MAX_METADATA_BITS) {
            throw new IdConfigurationException('Total metadata bits must be <= 28');
        }

        // we can process sequence per worker within this chunk of time
        $this->timestepNs = 1 << $this->totalMetaDataBits;
        $this->maxWorkers = 1 << $this->workersBits;
        $this->maxSequence = 1 << $this->sequenceBits;
    }
}
