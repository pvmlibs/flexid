<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\EncodedId;
use Pvmlibs\FlexId\Encoders\RotatedAlphabetEncoder;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Signers\Signer;
use Tests\Internal\TestingWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\EncodedId
 *
 * @internal
 */
final class IdEncodedTest extends TestCase
{
    public function testIdWithSigner(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);
        $encoder = new RotatedAlphabetEncoder();
        $signer = new Signer(new NativeSerializer(), 'PsQBSNyMoz60RpQnSKWBMg==');

        $encodedId = new EncodedId(
            flexIdGenerator: new FlexIdGenerator($workerResolver),
            encoder: $encoder,
            signer: $signer,
        );

        $id = $encodedId->generateId();
        $publicId = $encodedId->toPublicId($id);
        $this::assertSame($id, $encodedId->fromPublicId($publicId));
        $this::assertNotSame((string) $id, $publicId);
        $this::assertNotSame($publicId, $encoder->encode($id));
        $this::expectException(IdEncodeException::class);
        $encodedId->toPublicId(-100);
    }

    public function testIdWithoutSigner(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);
        $encoder = new RotatedAlphabetEncoder();

        $encodedId = new EncodedId(
            flexIdGenerator: new FlexIdGenerator($workerResolver),
            encoder: $encoder,
        );

        $id = $encodedId->generateId();
        $publicId = $encodedId->toPublicId($id);
        $this::assertSame($id, $encodedId->fromPublicId($publicId));
        $this::assertNotSame((string) $id, $publicId);
        $this::assertSame($publicId, $encoder->encode($id));
        $this::expectException(IdEncodeException::class);
        $encodedId->toPublicId(-100);
    }
}
