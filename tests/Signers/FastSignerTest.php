<?php

declare(strict_types=1);

namespace Signers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;
use Pvmlibs\FlexId\Signers\FastSigner;
use Tests\Internal\HasBackwardCompatibilityTesting;
use Tests\Internal\HasIdCharDistributionTesting;
use Tests\Signers\HasSignerTesting;

/**
 * @internal
 */
final class FastSignerTest extends TestCase
{
    use HasSignerTesting;
    use HasIdCharDistributionTesting;
    use HasBackwardCompatibilityTesting;

    public function testDefault(): void
    {
        $key = FastSigner::generateKey();
        $signer = new FastSigner(key: $key);
        $this->validateSignAndVerify($signer);
    }

    public function testWithVariableSignLength(): void
    {
        $key = FastSigner::generateKey();
        for ($i = 1; $i <= 16; $i++) {
            $signer = new FastSigner(key: $key, length: $i);
            $this->validateSignAndVerify($signer, 1000);
            $id = 'abcdefgh';
            $signed = $signer->getSignedId($id);
            $this::assertLessThanOrEqual(\strlen($id) + $i, \strlen($signed));
        }
    }

    public function testVerifyBadId(): void
    {
        $key = FastSigner::generateKey();
        $signer = new FastSigner(key: $key);
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('ghry*');
    }

    public function testSignEmptyId(): void
    {
        $key = FastSigner::generateKey();
        $signer = new FastSigner(key: $key);
        $this->expectException(IdSigningException::class);
        $signer->getSignedId('');
    }

    public function testGetIdFromEmptyData(): void
    {
        $key = FastSigner::generateKey();
        $signer = new FastSigner(key: $key);
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('');
    }

    public function testGetIdFromTooLongData(): void
    {
        $key = FastSigner::generateKey();
        $signer = new FastSigner(key: $key);
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('djclaorhfncajkdths83jdneqm06ns84ae');
    }

    public function testTamperWithId(): void
    {
        $key = FastSigner::generateKey();
        $signer = new FastSigner(key: $key);
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signed = \substr($signed, 1);
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned($signed);
    }

    public function testTamperWithSign(): void
    {
        $key = FastSigner::generateKey();
        $signer = new FastSigner(key: $key);
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signed .= 'e';
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned($signed);
    }

    public function testSignWithSalt(): void
    {
        $signer = new FastSigner(key: 'PsQBSNyMoz60RpQnSKWBMg==');
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signer2 = new FastSigner(key: 'PsQBSNyMoz60RpQnSKWBMg==', salt: 'abc');
        $signedWithSalt = $signer2->getSignedId($id);
        $this::assertNotSame($signed, $signedWithSalt);

        $this->expectException(IdVerifySignException::class);
        $signer2->getIdFromSigned($signed);
    }

    public function testBadKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FastSigner(
            key: 'PsQBSNyMoz60RpQnSKWB',
        );
    }

    public function testBadMaxSignLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FastSigner(
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
            length: 20,
        );
    }

    public function testEvenCharsDistribution(): void
    {
        $signer = new FastSigner(
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
        );

        $total = 1000;
        $ids = new \SplFixedArray($total);
        for ($i = 0; $i < $total; $i++) {
            $id = (string) random_int(0, PHP_INT_MAX);
            $signedId = $signer->getSignedId($id);
            $sign = \substr($signedId, \strlen($id));
            $ids[$i] = $sign;
        }
        $maxDeviations = $this->getMaxDeviation($ids, $signer->getAlphabet());
        // sign chars should be close to random, max deviation 2 times as random one from mean
        $this::assertLessThan($maxDeviations['random'] * 2, $maxDeviations['real']);
    }

    public function testBackwardCompatibility(): void
    {
        $signer = new FastSigner(
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
        );
        $this->validateBackwardCompatibility(fn (int $id): string => $signer->getSignedId((string) $id), PHP_INT_MAX, 'FastSigner');
    }
}
