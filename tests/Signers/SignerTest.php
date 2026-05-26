<?php

declare(strict_types=1);

namespace Tests\Signers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdSigningException;
use Pvmlibs\FlexId\Exceptions\IdVerifySignException;
use Pvmlibs\FlexId\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Serializers\FixedLengthSerializer;
use Pvmlibs\FlexId\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Signers\Signer;

/**
 * @internal
 */
final class SignerTest extends TestCase
{
    use HasSignerTesting;

    public function testWithSodiumSipHash(): void
    {
        $key = Signer::generateKey();
        $signer = new Signer(
            serializer: new NativeSerializer(),
            key: $key,
            hashAlgo: 'siphash-2-4',
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithSodiumBlake(): void
    {
        $key = Signer::generateKey();
        $signer = new Signer(
            serializer: new FixedLengthSerializer(),
            key: $key,
            hashAlgo: 'blake2b',
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithHashHmacSha256(): void
    {
        $key = Signer::generateKey();
        $signer = new Signer(
            serializer: new BCMathSerializer(),
            key: $key,
            hashAlgo: 'sha256',
        );
        $this->validateSignAndVerify($signer);
    }

    public function testWithNonCryptographyHash(): void
    {
        $key = Signer::generateKey();
        $signer = new Signer(
            serializer: new BCMathSerializer(),
            key: $key,
            hashAlgo: 'xxh64',
        );
        $this->validateSignAndVerify($signer, 2000, 1, 8);
    }

    public function testWithVariableSignLength(): void
    {
        $key = Signer::generateKey();
        for ($i = 1; $i <= 16; $i++) {
            $signer = new Signer(
                serializer: new BCMathSerializer(),
                key: $key,
                hashAlgo: 'xxh64',
                maxSignLength: $i,
            );
            $this->validateSignAndVerify($signer, 1000);
            $id = 'abcdefgh';
            $signed = $signer->getSignedId($id);
            $this::assertLessThanOrEqual(\strlen($id) + $i + 1, \strlen($signed)); // $i + separator
        }
    }

    public function testWithNoSeparator(): void
    {
        $key = Signer::generateKey();
        for ($i = 1; $i <= 17; $i++) {
            $signer = new Signer(
                serializer: new FixedLengthSerializer(),
                key: $key,
                hashAlgo: 'xxh64',
                separator: '',
                maxSignLength: $i,
            );
            $this->validateSignAndVerify($signer, 1000, 16, 16);

            $id = '0123456789abcdef';
            $signed = $signer->getSignedId($id);
            $this::assertSame($id, $signer->getIdFromSigned($signed));
            $this::assertNotSame($id, $signed);
            $this::assertSame(\strlen($id) + min(16, $i), \strlen($signed));
        }

        $id = '0123456789abcd';
        $signer = new Signer(serializer: new FixedLengthSerializer(), key: 'PsQBSNyMoz60RpQnSKWBMg==', separator: '');
        // this should pass, even if no separator and id is not fixed length, but sign is
        $signed = $signer->getSignedId($id);
        $this::assertNotSame($id, $signed);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signer = new Signer(serializer: new NativeSerializer(), key: 'PsQBSNyMoz60RpQnSKWBMg==', separator: '');
        // this should pass, id is fixed 16 chars
        $id = '0123456789abcdef';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));
        $this::assertNotSame($id, $signed);

        $id = '0123456789abcd';
        $signer = new Signer(serializer: new NativeSerializer(), key: 'PsQBSNyMoz60RpQnSKWBMg==', separator: '', maxSignLength: 1);
        // this should also pass, as maxSignLength is 1
        $signed = $signer->getSignedId($id);
        $this::assertNotSame($id, $signed);
        $this::assertSame(\strlen($id) + 1, \strlen($signed));
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $id = '0123456789abcd';
        $signer = new Signer(serializer: new FixedLengthSerializer(), key: 'PsQBSNyMoz60RpQnSKWBMg==', separator: '', maxSignLength: 2);
        // this should pass, as maxSignLength is 2, id is not fixed but sign uses fixed length serializer
        $signed = $signer->getSignedId($id);
        $this::assertNotSame($id, $signed);
        $this::assertSame(\strlen($id) + 2, \strlen($signed));
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signer = new Signer(serializer: new NativeSerializer(), key: 'PsQBSNyMoz60RpQnSKWBMg==', separator: '');
        $this::expectException(IdSigningException::class);
        $signer->getSignedId($id);
    }

    public function testVerifyBadId(): void
    {
        $signer = new Signer(
            serializer: new NativeSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
        );
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('ghry*');
    }

    public function testSignEmptyId(): void
    {
        $signer = new Signer(
            serializer: new NativeSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
        );
        $this->expectException(IdSigningException::class);
        $signer->getSignedId('');
    }

    public function testGetIdFromEmptyData(): void
    {
        $signer = new Signer(
            serializer: new NativeSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
        );
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned('');
    }

    public function testTamperWithId(): void
    {
        $signer = new Signer(
            serializer: new NativeSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
        );
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signed = \substr($signed, 1);
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned($signed);
    }

    public function testTamperWithSign(): void
    {
        $signer = new Signer(
            serializer: new NativeSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
        );
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signed .= 'e';
        $this->expectException(IdVerifySignException::class);
        $signer->getIdFromSigned($signed);
    }

    public function testSignWithSalt(): void
    {
        $signer = new Signer(
            serializer: new NativeSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
        );
        $id = 'sdflk4sfk';
        $signed = $signer->getSignedId($id);
        $this::assertSame($id, $signer->getIdFromSigned($signed));

        $signer2 = new Signer(
            serializer: new NativeSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
            salt: 'abc',
        );
        $signedWithSalt = $signer2->getSignedId($id);
        $this::assertNotSame($signed, $signedWithSalt);

        $this->expectException(IdVerifySignException::class);
        $signer2->getIdFromSigned($signed);
    }

    public function testTooLongSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: new NativeSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
            separator: '-_',
        );
    }

    public function testBadSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: new BCMathSerializer(alphabet: 'absj-'),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
            separator: '-',
        );
    }

    public function testBadKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: new BCMathSerializer(alphabet: 'absj-'),
            key: 'PsQBSNyMoz60RpQnSKWB',
            separator: '-',
        );
    }

    public function testBadMaxSignLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(
            serializer: new BCMathSerializer(),
            key: 'PsQBSNyMoz60RpQnSKWBMg==',
            separator: '-',
            maxSignLength: 0,
        );
    }
}
