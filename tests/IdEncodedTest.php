<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\EncodedId;
use Pvmlibs\FlexId\Encoders\PseudoRandomEncoder;
use Pvmlibs\FlexId\Exceptions\IdEncodeException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Tests\Internal\TestingWorkerResolver;

/**
 * @covers \Pvmlibs\FlexId\EncodedId
 *
 * @internal
 */
final class IdEncodedTest extends TestCase
{
    public function testId(): void
    {
        $workerResolver = new TestingWorkerResolver();
        $workerResolver->setWorker(fn () => 1);
        $encodedId = new EncodedId(
            flexIdGenerator: new FlexIdGenerator($workerResolver),
            encoder: new PseudoRandomEncoder(),
        );

        $id = $encodedId->generateId();
        $publicId = $encodedId->toPublicId($id);
        $this::assertSame($id, $encodedId->fromPublicId($publicId));
        $this::assertNotSame((string) $id, $publicId);
        $this::expectException(IdEncodeException::class);
        $encodedId->toPublicId(-100);
    }
}
