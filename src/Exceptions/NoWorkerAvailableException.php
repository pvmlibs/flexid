<?php

namespace Pvmlibs\FlexId\Exceptions;

final class NoWorkerAvailableException extends \RuntimeException
{
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
