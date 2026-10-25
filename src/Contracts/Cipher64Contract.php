<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Contracts;

interface Cipher64Contract
{
    public function encrypt(int $data): int;
    public function decrypt(int $data): int;
}
