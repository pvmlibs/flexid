<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

interface HMACContract
{
    /**
     * @throws \InvalidArgumentException
     */
    public function setup(int $output64bitWords): void;

    /**
     * @return list<int>
     */
    public function sign(string $data, ?int $id = null): array;
}
