<?php

declare(strict_types=1);

namespace Tests\Signers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;
use Pvmlibs\FlexId\HMAC\Blake2HMACSodiumExt;
use Pvmlibs\FlexId\HMAC\Sha256HMAC;
use Pvmlibs\FlexId\HMAC\SiphashHMAC;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\CustomSerializer;
use Pvmlibs\FlexId\Serializers\HexSerializer;
use Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersBCMath;
use Pvmlibs\FlexId\Signers\Signer;
use Pvmlibs\FlexId\Tools\KeyGen;
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
        $key = KeyGen::generate();
        $hmac = new SiphashHMAC($key);
        $signer = new Signer(
            serializer: new BaseSerializer(),
            hmac: $hmac,
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithSodiumBlake(): void
    {
        $key = KeyGen::generate();
        $hmac = new Blake2HMACSodiumExt($key);
        $signer = new Signer(
            serializer: new BaseSerializer(),
            hmac: $hmac,
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithHashHmacSha256(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $signer = new Signer(
            serializer: new CustomSerializer(new FullRangeIntegersBCMath()),
            hmac: $hmac,
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithHexSerializer(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $signer = new Signer(
            serializer: new HexSerializer(),
            hmac: $hmac,
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithVariableSignLength(): void
    {
        $key = KeyGen::generate();
        $hmac = new SiphashHMAC($key);
        $baseSerializer = new BaseSerializer();
        for ($i = 1; $i <= $baseSerializer->getMaxEncodedLength(); $i++) {
            $signer = new Signer(
                serializer: $baseSerializer,
                hmac: $hmac,
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
                serializer: $customSerializer,
                hmac: $hmac,
                maxSignLength: $i,
                onlyPositiveRange: true,
            );
            $this->validateSignAndVerify($signer, 1000);
            $id = 'abcdefgh';
            $signed = $signer->getSignedId($id);
            $this::assertLessThanOrEqual(\strlen($id) + $i + 1, \strlen($signed)); // $i + separator
        }

        $signer = new Signer(
            serializer: $baseSerializer,
            hmac: $hmac,
            maxSignLength: 1,
        );
        $id = 'abcdefgh';
        $signed = $signer->getSignedId($id);
        $this::assertSame(\strlen($id) + 1, \strlen($signed)); // $i + 1 char sign, no separator

        $this::expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: $baseSerializer,
            hmac: $hmac,
            maxSignLength: $baseSerializer->getMaxEncodedLength() + 1,
        );
    }

    public function testWithExtendedSignBits(): void
    {
        $key = KeyGen::generate();
        $hmac = new Sha256HMAC($key);
        $bits = [128, 192, 256];
        $serializer = new BaseSerializer();
        foreach ($bits as $bitSize) {
            for ($i = 1; $i <= $serializer->getMaxEncodedLength(); $i++) {
                $signer = new Signer(
                    serializer: new BaseSerializer(),
                    hmac: $hmac,
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
                    serializer: new CustomSerializer(),
                    hmac: $hmac,
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
            serializer: $serializer,
            hmac: $hmac,
            signBits: 123,
        );
    }

    public function testUnsupportedBitSizeByHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
            signBits: 128,
        );
    }

    public function testReducedBitsHash(): void
    {
        $signer = new Signer(
            serializer: new CustomSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
            onlyPositiveRange: true,
        );
        $this->validateSignAndVerify($signer, 1000);
    }

    public function testVerifyBadId(): void
    {
        $signer = new Signer(
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
        );
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('ghry*');
    }

    public function testVerifyBadIdNulls(): void
    {
        $signer = new Signer(
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
        );
        $this->expectException(IdBadSignException::class);
        $signer->getIdFromSigned(str_repeat("\x00", 16));
    }

    public function testSignEmptyId(): void
    {
        $signer = new Signer(
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
        );
        $this->expectException(IdSigningException::class);
        $signer->getSignedId('');
    }

    public function testGetIdFromEmptyData(): void
    {
        $signer = new Signer(
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
        );
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('');
    }

    public function testGetIdFromTooLongData(): void
    {
        $signer = new Signer(
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
        );
        $this->expectException(IdBadSignException::class);
        $signer->getIdFromSigned('djclaorhfncajkdths83jdneqm06ns84ae');
    }

    public function testTamperWithId(): void
    {
        $signer = new Signer(
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
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
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
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
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
        );
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signer2 = new Signer(
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
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
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
            separator: '-_',
        );
    }

    public function testBadSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: new CustomSerializer(fullRangeIntegerOperations: new FullRangeIntegersBCMath(), alphabet: 'absj-'),
            hmac: new SiphashHMAC(KeyGen::generate()),
            separator: '-',
        );
    }

    public function testBadKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: new CustomSerializer(fullRangeIntegerOperations: new FullRangeIntegersBCMath(), alphabet: 'absj-'),
            hmac: new SiphashHMAC(KeyGen::generate()),
            separator: '-',
        );
    }

    public function testBadMaxSignLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: new CustomSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
            separator: '-',
            maxSignLength: 0,
        );
    }

    public function testEvenCharsDistribution(): void
    {
        $signer = new Signer(
            serializer: new HexSerializer(),
            hmac: new SiphashHMAC(KeyGen::generate()),
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
            serializer: new BaseSerializer(),
            hmac: new SiphashHMAC('PsQBSNyMoz60RpQnSKWBMg=='),
        );
        $this->validateBackwardCompatibility(fn (int $id): string => $signer->getSignedId((string) $id), 0, PHP_INT_MAX, 'Signer');
        $this->validateBackwardCompatibility(fn (int $id): string => $signer->getSignedId((string) $id, 'abcd'), 0, PHP_INT_MAX, 'SignerAD');
    }
}
