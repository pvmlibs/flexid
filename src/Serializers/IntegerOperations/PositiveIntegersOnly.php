<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers\IntegerOperations;

use Pvmlibs\FlexId\Contracts\IntegerOperationsContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;

class PositiveIntegersOnly implements IntegerOperationsContract
{
    public function divmod(int $num, int $div): array
    {
        if ($num >= 0) {
            return [\intdiv($num, $div), $num % $div];
        }

        throw new IdEncodeException('Id out of range');
    }

    public function addmul(int $add, int $mul1, int $mul2): int
    {
        $numberInt = $mul1 * $mul2 + $add;

        if (\is_float($numberInt) === false) { // @phpstan-ignore function.impossibleType,identical.alwaysTrue (this can be float)
            return $numberInt;
        }
        throw new IdDecodeException('Id out of range'); // @phpstan-ignore deadCode.unreachable
    }
}
