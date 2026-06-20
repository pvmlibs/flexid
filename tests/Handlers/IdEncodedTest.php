<?php

declare(strict_types=1);

namespace Handlers;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdBadSignException;
use Pvmlibs\FlexId\Exceptions\IdDecodeException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\IdHandlers\EncodedId;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\CustomSerializer;
use Pvmlibs\FlexId\Signers\Signer;
use Tests\Internal\TestingWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\IdHandlers\EncodedId
 *
 * @internal
 */
final class IdEncodedTest extends TestCase
{
    public function testIdWithSigner(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);
        $serializer = new CustomSerializer();
        $signer = new Signer('PsQBSNyMoz60RpQnSKWBMg==', new BaseSerializer());

        $encodedId = new EncodedId(
            serializer: $serializer,
            signer: $signer,
            generator: new FlexIdGenerator($workerResolver),
        );

        $id = $encodedId->generateId();
        $publicId = $encodedId->toPublicId($id);
        $this::assertSame($id, $encodedId->fromPublicId($publicId));
        $this::assertNotSame((string) $id, $publicId);
        $this::assertNotSame($publicId, $serializer->serialize($id));

        $publicIdWithAd = $encodedId->toPublicId($id, 'abc');
        $this::assertNotSame($publicId, $publicIdWithAd);
        $this::assertSame($id, $encodedId->fromPublicId($publicIdWithAd, 'abc'));

        try {
            $encodedId->fromPublicId('sadkjhvclofjgpoisjfelasdjlancxz');
            $this::fail('Id is too long');
        } catch (IdDecodeException) {
        }

        $this::expectException(IdBadSignException::class);
        $encodedId->fromPublicId($publicIdWithAd, 'abcef');
    }

    public function testIdWithoutSigner(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);
        $serializer = new CustomSerializer();

        $encodedId = new EncodedId(
            serializer: $serializer,
            generator: new FlexIdGenerator($workerResolver),
        );

        $id = $encodedId->generateId();
        $publicId = $encodedId->toPublicId($id);
        $this::assertSame($id, $encodedId->fromPublicId($publicId));
        $this::assertNotSame((string) $id, $publicId);
        $this::assertSame($publicId, $serializer->serialize($id));
    }

    public function testIdWithNoGenerator(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);
        $serializer = new CustomSerializer();

        $encodedId = new EncodedId(
            serializer: $serializer,
            generator: null,
        );

        $this::expectException(\RuntimeException::class);
        $encodedId->generateId();
    }
}
