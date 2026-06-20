<?php

declare(strict_types=1);

namespace Tests\Signers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\CustomSerializer;
use Pvmlibs\FlexId\Serializers\HexSerializer;
use Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersBCMath;
use Pvmlibs\FlexId\Signers\Signer;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasCharDistributionTesting;

/**
 * @internal
 */
final class SignerTest extends TestCase
{
    use HasSignerTesting;
    use HasCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testWithSodiumSipHash(): void
    {
        $key = Signer::generateSecret();
        $signer = new Signer(
            secret: $key,
            serializer: new BaseSerializer(),
            hashAlgo: 'siphash-2-4',
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithSodiumBlake(): void
    {
        $key = Signer::generateSecret();
        $signer = new Signer(
            secret: $key,
            serializer: new BaseSerializer(),
            hashAlgo: 'blake2b',
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithHashHmacSha256(): void
    {
        $key = Signer::generateSecret();
        $signer = new Signer(
            secret: $key,
            serializer: new CustomSerializer(new FullRangeIntegersBCMath()),
            hashAlgo: 'sha256',
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithNonCryptographyHash(): void
    {
        $key = Signer::generateSecret();
        $this::expectException(\InvalidArgumentException::class);
        new Signer(
            secret: $key,
            serializer: new CustomSerializer(),
            hashAlgo: 'xxh64',
        );
    }

    public function testWithHexSerializer(): void
    {
        $key = Signer::generateSecret();
        $signer = new Signer(
            secret: $key,
            serializer: new HexSerializer(),
            hashAlgo: 'siphash-2-4',
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithVariableSignLength(): void
    {
        $key = Signer::generateSecret();
        $baseSerializer = new BaseSerializer();
        for ($i = 1; $i <= $baseSerializer->getMaxEncodedLength(); $i++) {
            $signer = new Signer(
                secret: $key,
                serializer: $baseSerializer,
                hashAlgo: 'siphash-2-4',
                maxSignLength: $i,
            );
            $this->validateSignAndVerify($signer, 1000);
            $id = 'abcdefgh';
            $signed = $signer->getSignedId($id);
            $this::assertLessThanOrEqual(\strlen($id) + $i + 1, \strlen($signed)); // $i + separator
        }
        $customSerializer = new CustomSerializer();
        // check for 63 bits
        for ($i = 1; $i <= $customSerializer->getMaxEncodedLength(); $i++) {
            $signer = new Signer(
                secret: $key,
                serializer: $customSerializer,
                hashAlgo: 'siphash-2-4',
                maxSignLength: $i,
                onlyPositiveRange: true,
            );
            $this->validateSignAndVerify($signer, 1000);
            $id = 'abcdefgh';
            $signed = $signer->getSignedId($id);
            $this::assertLessThanOrEqual(\strlen($id) + $i + 1, \strlen($signed)); // $i + separator
        }

        $signer = new Signer(
            secret: $key,
            serializer: $baseSerializer,
            hashAlgo: 'siphash-2-4',
            maxSignLength: 1,
        );
        $id = 'abcdefgh';
        $signed = $signer->getSignedId($id);
        $this::assertSame(\strlen($id) + 1, \strlen($signed)); // $i + 1 char sign, no separator

        $this::expectException(\InvalidArgumentException::class);
        new Signer(
            secret: $key,
            serializer: $baseSerializer,
            hashAlgo: 'siphash-2-4',
            maxSignLength: $baseSerializer->getMaxEncodedLength() + 1,
        );
    }

    public function testWithExtendedSignBits(): void
    {
        $key = Signer::generateSecret();
        $bits = [128, 192, 256];
        $serializer = new BaseSerializer();
        foreach ($bits as $bitSize) {
            for ($i = 1; $i <= $serializer->getMaxEncodedLength(); $i++) {
                $signer = new Signer(
                    secret: $key,
                    serializer: new BaseSerializer(),
                    maxSignLength: $i,
                    signBits: $bitSize,
                );
                $this->validateSignAndVerify($signer, 2000, 3, 16);
                $id = 'abcdefgh';
                $signed = $signer->getSignedId($id);
                $this::assertLessThanOrEqual(\strlen($id) + (\intdiv($bitSize, 64) + 1) * $i, \strlen($signed)); // (sign parts + separator )*$i
            }
        }
        $serializer = new CustomSerializer();
        // check for 63 bits
        foreach ($bits as $bitSize) {
            for ($i = 1; $i <= $serializer->getMaxEncodedLength(); $i++) {
                $signer = new Signer(
                    secret: $key,
                    serializer: new CustomSerializer(),
                    maxSignLength: $i,
                    signBits: $bitSize,
                    onlyPositiveRange: true,
                );
                $this->validateSignAndVerify($signer, 2000, 3, 16);
                $id = 'abcdefgh';
                $signed = $signer->getSignedId($id);
                $this::assertLessThanOrEqual(\strlen($id) + (\intdiv($bitSize, 64) + 1) * $i, \strlen($signed)); // (sign parts + separator )*$i
            }
        }
        $this::expectException(\InvalidArgumentException::class);
        new Signer(
            secret: $key,
            serializer: $serializer,
            hashAlgo: 'siphash-2-4',
            signBits: 123,
        );
    }

    public function testUnsupportedBitSizeByHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
            hashAlgo: 'siphash-2-4',
            signBits: 128,
        );
    }

    public function testReducedBitsHash(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new CustomSerializer(),
            hashAlgo: 'siphash-2-4',
            onlyPositiveRange: true,
        );
        $this->validateSignAndVerify($signer, 1000);
    }

    public function testVerifyBadId(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('ghry*');
    }

    public function testVerifyBadIdNulls(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $this->expectException(IdBadSignException::class);
        $signer->getIdFromSigned(str_repeat("\x00", 16));
    }

    public function testSignEmptyId(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $this->expectException(IdSigningException::class);
        $signer->getSignedId('');
    }

    public function testGetIdFromEmptyData(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('');
    }

    public function testGetIdFromTooLongData(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $this->expectException(IdBadSignException::class);
        $signer->getIdFromSigned('djclaorhfncajkdths83jdneqm06ns84ae');
    }

    public function testTamperWithId(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signed = \substr($signed, 1);
        $this->expectException(IdBadSignException::class);
        $signer->getIdFromSigned($signed);
    }

    public function testTamperWithSign(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signed .= 'e';
        $this->expectException(IdBadSignException::class);
        $signer->getIdFromSigned($signed);
    }

    public function testSignDifferentKeys(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signer2 = new Signer(
            secret: 'rCl29//aZ51LjLQZKUbMUA==',
            serializer: new BaseSerializer(),
        );
        $signed2 = $signer2->getSignedId($id);
        $this::assertNotSame($signed, $signed2);

        $this->expectException(IdBadSignException::class);
        $signer2->getIdFromSigned($signed);
    }

    public function testTooLongSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
            separator: '-_',
        );
    }

    public function testBadSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new CustomSerializer(fullRangeIntegerOperations: new FullRangeIntegersBCMath(), alphabet: 'absj-'),
            separator: '-',
        );
    }

    public function testBadKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWB',
            serializer: new CustomSerializer(fullRangeIntegerOperations: new FullRangeIntegersBCMath(), alphabet: 'absj-'),
            separator: '-',
        );
    }

    public function testBadMaxSignLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new CustomSerializer(),
            separator: '-',
            maxSignLength: 0,
        );
    }

    public function testEvenCharsDistribution(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new HexSerializer(),
        );

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $id = (string) random_int(PHP_INT_MIN, PHP_INT_MAX);
            $signedId = $signer->getSignedId($id);
            $sign = \substr($signedId, \strlen($id));
            $ids[$i] = $sign;
        }
        $maxDeviations = $this->getMaxDeviation($ids, $signer->getAlphabet());
        // sign chars should be close to random, max deviation 2 times as random one from mean
        $this::assertLessThan(
            $maxDeviations['random'] * 3,
            $maxDeviations['real'],
            sprintf(
                'Max deviation of %s (%f) is above limit %f, mean %f',
                $maxDeviations['mostFrequentChar'],
                $maxDeviations['real'],
                $maxDeviations['random'] * 3,
                $maxDeviations['mean'],
            ),
        );
    }

    public function testBackwardCompatibility(): void
    {
        $signer = new Signer(
            secret: 'PsQBSNyMoz60RpQnSKWBMg==',
            serializer: new BaseSerializer(),
        );
        $this->validateBackwardCompatibility(fn (int $id): string => $signer->getSignedId((string) $id), 0, PHP_INT_MAX, 'Signer');
        $this->validateBackwardCompatibility(fn (int $id): string => $signer->getSignedId((string) $id, 'abcd'), 0, PHP_INT_MAX, 'SignerAD');
    }
}
