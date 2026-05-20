<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Exceptions;

final class NoWorkerAvailableException extends \RuntimeException
{
    /**
     * @param int $lockTimeUs This must be in real us, when using timestampBitshift make sure you shifted it
     */
    public function __construct(
        public readonly int $maxWorkers,
        public readonly int $groupId,
        public readonly int $lockTimeUs = 0,
        public readonly ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $this->previous?->getMessage() ?? "No workers available (limit {$this->maxWorkers}) for group {$groupId}",
            previous: $this->previous,
        );
    }
}
