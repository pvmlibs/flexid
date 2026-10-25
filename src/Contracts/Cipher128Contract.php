<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

interface Cipher128Contract
{
    /**
     * @return list<int>
     */
    public function encrypt(int $lowPart, int $highPart): array;

    /**
     * @return list<int>
     */
    public function decrypt(int $lowPart, int $highPart): array;
}
