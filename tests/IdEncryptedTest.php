<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\EncryptedId;
use Pvmlibs\FlexId\Encrypters\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Tests\Internal\TestingWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\EncryptedId
 *
 * @internal
 */
final class IdEncryptedTest extends TestCase
{
    public function testId(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);
        $encodedId = new EncryptedId(
            flexIdGenerator: new FlexIdGenerator($workerResolver),
            encrypter: new Sparx64Encrypter(
                secret: Sparx64Encrypter::generateSecret(),
                serializer: new BCMathSerializer(),
            ),
        );

        $id = $encodedId->generateId();
        $publicId = $encodedId->toPublicId($id);
        $this::assertSame($id, $encodedId->fromPublicId($publicId));
        $this::assertNotSame((string) $id, $publicId);
        $this::expectException(IdEncodeException::class);
        $encodedId->toPublicId(-100);
    }
}
