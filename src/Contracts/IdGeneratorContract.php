<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

interface IdGeneratorContract
{
    public function id(): int;
}
