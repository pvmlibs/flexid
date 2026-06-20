<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Serializers\IntegerOperations;

use Pvmlibs\FlexId\Contracts\IntegerOperationsContract;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;

class FullRangeIntegersGmp implements IntegerOperationsContract
{
    public function __construct()
    {
        if (extension_loaded('gmp') === false) {
            throw new \RuntimeException('GMP extension not installed');
        }
    }

    public function divmod(int $num, int $div): array
    {
        if ($num < 0) {
            $positive = $num & 0x7FFFFFFFFFFFFFFF; // ~(1 << 63)

            if ($positive !== PHP_INT_MAX) {
                $num = \gmp_add($positive + 1, '9223372036854775807'); // PHP_INT_MAX
            } else {
                $num = '18446744073709551615'; // 2 * PHP_INT_MAX + 1
            }

            $res = \gmp_div_qr($num, $div);

            return [
                (int) $res[0], // @phpstan-ignore offsetAccess.notFound
                (int) $res[1], // @phpstan-ignore offsetAccess.notFound
            ];
        }

        return [\intdiv($num, $div), $num % $div]; // for positive numbers
    }

    public function addmul(int $add, int $mul1, int $mul2): int
    {
        $numberInt = $mul1 * $mul2 + $add;

        if (\is_float($numberInt) === false) { // @phpstan-ignore function.impossibleType,identical.alwaysTrue (this can be float)
            // we're still in signed int range, can go without gmp
            return $numberInt;
        }

        $stringResult = \gmp_sub( // @phpstan-ignore deadCode.unreachable
            \gmp_add((string) $add, \gmp_mul((string) $mul1, (string) $mul2)),
            '18446744073709551616',
        );

        $intResult = (int) $stringResult;
        if ((string) $stringResult !== (string) $intResult) {
            throw new IdDecodeException('Id out of range');
        }

        return $intResult;
    }
}
