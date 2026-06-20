<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

interface IntegerOperationsContract
{
    /**
     * @return array<int, int>
     */
    public function divmod(int $num, int $div): array;

    public function addmul(int $add, int $mul1, int $mul2): int;
}
