<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\EncryptedId;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Signers\Signer;
use Tests\Internal\TestingWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\EncryptedId
 *
 * @internal
 */
final class IdEncryptedTest extends TestCase
{
    public function testIdWithSigner(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);

        $encrypter = new Sparx64Encrypter(
            secret: Sparx64Encrypter::generateSecret(),
            serializer: new BCMathSerializer(),
        );
        $signer = new Signer(new NativeSerializer(), 'PsQBSNyMoz60RpQnSKWBMg==');

        $encodedId = new EncryptedId(
            flexIdGenerator: new FlexIdGenerator($workerResolver),
            encrypter: $encrypter,
            signer: $signer,
        );

        $id = $encodedId->generateId();
        $publicId = $encodedId->toPublicId($id);
        $this::assertSame($id, $encodedId->fromPublicId($publicId));
        $this::assertNotSame((string) $id, $publicId);
        $this::assertNotSame($publicId, $encrypter->encrypt($id));
        $this::expectException(IdEncodeException::class);
        $encodedId->toPublicId(-100);
    }

    public function testIdWithoutSigner(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);

        $encrypter = new Sparx64Encrypter(
            secret: Sparx64Encrypter::generateSecret(),
            serializer: new BCMathSerializer(),
        );

        $encodedId = new EncryptedId(
            flexIdGenerator: new FlexIdGenerator($workerResolver),
            encrypter: $encrypter,
        );

        $id = $encodedId->generateId();
        $publicId = $encodedId->toPublicId($id);
        $this::assertSame($id, $encodedId->fromPublicId($publicId));
        $this::assertNotSame((string) $id, $publicId);
        $this::assertSame($publicId, $encrypter->encrypt($id));
        $this::expectException(IdEncodeException::class);
        $encodedId->toPublicId(-100);
    }
}
