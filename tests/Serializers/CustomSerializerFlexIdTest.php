<?php

declare(strict_types=1);

namespace Serializers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Serializers\Implementations\BaseSerializerPHP;
use Pvmlibs\FlexId\Serializers\Implementations\CustomSerializerFlexIdExt;
use Pvmlibs\FlexId\Serializers\Implementations\CustomSerializerPHP;
use Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersBCMath;
use Pvmlibs\FlexId\Tools\Alphabets;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;
use Tests\Serializers\HasSerializerTesting;

/**
 * @internal
 */
final class CustomSerializerFlexIdTest extends TestCase
{
    use HasSerializerTesting;
    use HasBackwardCompatibilityTesting;
    use HasCharDistributionTesting;

    public function setUp(): void
    {
        if (\extension_loaded('flexid_utils') === false) {
            $this::markTestSkipped('Flexid not loaded');
        }
    }

    public function testSerializeDeserialize(): void
    {
        $serializer = new CustomSerializerFlexIdExt();
        $this->validateSerializeDeserialize($serializer);

        foreach ($this->alphabets() as $alphabet) {
            $serializer = new CustomSerializerFlexIdExt($alphabet);
            $this->validateSerializeDeserialize(serializer: $serializer, incrementalRange: 100, withRandom: false);

            $this->validateOutOfRange($alphabet, $serializer, -1);
        }
    }

    public function testEmptyAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomSerializerFlexIdExt(alphabet: '');
    }

    public function testTooSmallAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomSerializerFlexIdExt(alphabet: 's');
    }

    public function testNotUniqueAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomSerializerFlexIdExt(alphabet: 'ssd');
    }

    public function testAlphabetContainsMultibyteChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomSerializerFlexIdExt(alphabet: 'sbâ');
    }

    public function testEmptyId(): void
    {
        $serializer = new CustomSerializerFlexIdExt();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('');
    }

    public function testBadCharactersInId(): void
    {
        $serializer = new CustomSerializerFlexIdExt();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('-');
    }

    public function testBadCharactersInIdMiddle(): void
    {
        $serializer = new CustomSerializerFlexIdExt();
        $this->expectException(IdDecodeException::class);
        $serializer->deserialize('Db-Db');
    }

    public function testInteroperabilityCustomSerializer(): void
    {
        foreach ($this->alphabets() as $alphabet) {
            $customSerializerFlexId = new CustomSerializerFlexIdExt($alphabet);
            $customSerializerPHP = new CustomSerializerPHP(new FullRangeIntegersBCMath(), $alphabet);
            $this::assertSame($this->runDeterministicBatch($customSerializerPHP), $this->runDeterministicBatch($customSerializerFlexId));
            $this::assertSame($customSerializerPHP->getMaxEncodedLength(), $customSerializerFlexId->getMaxEncodedLength());
        }
    }

    public function testInteroperabilityBaseSerializer(): void
    {
        $baseSerializerFlexId = new CustomSerializerFlexIdExt(Alphabets::BASE32_SAFE_ALPHABET);
        $baseSerializerPHP = new BaseSerializerPHP();

        $this::assertSame($this->runDeterministicBatch($baseSerializerPHP), $this->runDeterministicBatch($baseSerializerFlexId));
        $this::assertSame($baseSerializerPHP->getMaxEncodedLength(), $baseSerializerFlexId->getMaxEncodedLength());
    }

    public function testBackwardCompatibilityFullRange(): void
    {
        $serializer = new CustomSerializerFlexIdExt();
        $this->validateBackwardCompatibility(fn (int $id): string => $serializer->serialize($id), PHP_INT_MIN, PHP_INT_MAX, 'CustomAlphabetSerializerFlexId');
    }
}
