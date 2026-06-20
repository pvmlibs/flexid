<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

interface WorkerResolverHasTTLContract
{
    public function getTTLms(): int;
}
