<?php

declare(strict_types=1);

namespace Handlers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Encrypters\AesEncrypter;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\Encrypters\XChaCha20Encrypter;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\Exceptions\IdDecryptException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\IdHandlers\EncryptedId;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Signers\Signer;
use Tests\Internal\TestingWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\IdHandlers\EncryptedId
 *
 * @internal
 */
final class IdEncryptedTest extends TestCase
{
    public function testIdWithSignerAndSparx64(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);

        $encrypter = new Sparx64Encrypter(
            secret: Sparx64Encrypter::generateSecret(),
            serializer: new BaseSerializer(),
        );
        $signer = new Signer('PsQBSNyMoz60RpQnSKWBMg==', new BaseSerializer());

        $encryptedId = new EncryptedId(
            encrypter: $encrypter,
            signer: $signer,
            generator: new FlexIdGenerator($workerResolver),
        );

        $ad = 'test';
        $id = $encryptedId->generateId();
        $publicId = $encryptedId->toPublicId($id, $ad);
        $this::assertSame($id, $encryptedId->fromPublicId($publicId, $ad));

        try {
            $encryptedId->fromPublicId('sadkjhvclofjgpoisjfelasdjlancxzru');
            $this::fail('Id is too long');
        } catch (IdDecodeException) {
        }

        $this::expectException(IdBadSignException::class);
        $encryptedId->fromPublicId($publicId, ''); // signer will throw error
    }

    public function testIdWithoutSignerAndSparx64(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);

        $encrypter = new Sparx64Encrypter(
            secret: Sparx64Encrypter::generateSecret(),
            serializer: new BaseSerializer(),
        );

        $encryptedId = new EncryptedId(
            encrypter: $encrypter,
            generator: new FlexIdGenerator($workerResolver),
        );

        $ad = 'test';
        $id = $encryptedId->generateId();
        $publicId = $encryptedId->toPublicId($id, $ad);
        $this::assertSame($id, $encryptedId->fromPublicId($publicId, $ad));
        $this::assertSame($id, $encryptedId->fromPublicId($publicId, 'test2')); // ad is ignored with Sparx64 and no signer
    }

    public function testIdWithNoGenerator(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);

        $encrypter = new XChaCha20Encrypter(
            secret: XChaCha20Encrypter::generateSecret(),
        );

        $encryptedId = new EncryptedId(
            encrypter: $encrypter,
            generator: null,
        );

        $this::expectException(\RuntimeException::class);
        $encryptedId->generateId();
    }

    public function testIdWithXChaCha(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);

        $encrypter = new XChaCha20Encrypter(
            secret: XChaCha20Encrypter::generateSecret(),
        );

        $encryptedId = new EncryptedId(
            encrypter: $encrypter,
            generator: new FlexIdGenerator($workerResolver),
        );

        $ad = 'test';
        $id = $encryptedId->generateId();
        $publicId = $encryptedId->toPublicId($id, $ad);
        $this::assertSame($id, $encryptedId->fromPublicId($publicId, $ad));

        $this::expectException(IdDecryptException::class);
        $encryptedId->fromPublicId($publicId, '');
    }

    public function testIdWithAes(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);

        $encrypter = new AesEncrypter(
            secret: AesEncrypter::generateSecret(),
        );

        $encryptedId = new EncryptedId(
            encrypter: $encrypter,
            generator: new FlexIdGenerator($workerResolver),
        );

        $ad = 'test';
        $id = $encryptedId->generateId();
        $publicId = $encryptedId->toPublicId($id, $ad);
        $this::assertSame($id, $encryptedId->fromPublicId($publicId, $ad));

        $this::expectException(IdBadSignException::class);
        $encryptedId->fromPublicId($publicId, '');
    }
}
