<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Resolvers;

interface WorkerResolverHasTTLContract
{
    public function getTTLms(): int;
}
