<?php

declare(strict_types=1);

namespace Tests\Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class BaseSerializerTest extends TestCase
{
    use HasSerializerTesting;
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testSerializeDeserializeWitAlphabets(): void
    {
        $alphabets = [
            'ab' => 1,
            'abcd' => 3,
            'FDxkwdMK' => 1,
            'FDxkwdMKQRGgCBPL' => 15,
            'FDxkwdMKQRGgCBPLpYmvVJyXZbjczWqf' => 15,
            'R9jHJSAcLBfFroKqTzXvgxtNbsp83DEkVMIn-y0m5hdG_P47awQl26uYZUeW1iCO' => 15,
        ];
        foreach ($alphabets as $alphabet => $length) {
            $this->validateWithAlphabet($alphabet, $length);
        }
    }

    private function validateWithAlphabet(string $alphabet, int $maxLastPos): void
    {
        $serializer = new BaseSerializer($alphabet);
        $this->validateSerializeDeserialize($serializer);

        try {
            // id too long
            $repeat = \intdiv($serializer->getMaxEncodedLength(), \strlen($alphabet)) + 1;
            $serializer->deserialize(\substr(\str_repeat($alphabet, $repeat), 0, $serializer->getMaxEncodedLength()) . $alphabet[0]);
            $this::fail("Id should be out of range, alphabet {$alphabet}");
        } catch (IdDecodeException $e) {
        }
        try {
            // check only if max last position is not the last in alphabet
            if ($maxLastPos < \strlen($alphabet) - 1) {
                // within length but still out of range
                $serializer->deserialize(\str_repeat(\substr($alphabet, -1), $serializer->getMaxEncodedLength() - 1) . $alphabet[$maxLastPos + 1]);
                $this::fail("Id should be out of range, alphabet {$alphabet}");
            }
        } catch (IdDecodeException $e) {
        }
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializer('');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializer('s');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializer('ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BaseSerializer('sbâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new BaseSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new BaseSerializer();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('-');
    }

    public function testEvenCharsDistribution(): void
    {
        $encoder = new BaseSerializer();

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $ids[$i] = $encoder->serialize(\random_int(PHP_INT_MIN, PHP_INT_MAX));
        }

        $maxDeviations = $this->getMaxDeviation($ids, $encoder->getAlphabet());
        // max deviation 5 times as random one
        $this::assertLessThan($maxDeviations['random'] * 5, $maxDeviations['real']);
    }

    public function testBackwardCompatibility(): void
    {
        $encoder = new BaseSerializer();
        $this->validateBackwardCompatibility(fn (int $id): string => $encoder->serialize($id), PHP_INT_MIN, PHP_INT_MAX, 'BaseSerializer');
    }
}
