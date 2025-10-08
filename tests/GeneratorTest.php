<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\NoWorkerAvailableException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Tests\Internal\TestingWorkerResolver;

/**
 * @internal
 */
final class GeneratorTest extends TestCase
{
    public function testGeneratorIdMetadata(): void
    {
        $workerBits = 5;
        $groupBits = 2;
        $sequenceBits = 2;
        $groupId = 1;
        $workerTimeoutMs = 3;
        $resolver = new TestingWorkerResolver(
            workerTimeoutMs: $workerTimeoutMs,
            groupId: $groupId,
            workersBits: $workerBits,
            sequenceBits: $sequenceBits,
            groupsBits: $groupBits,
        );
        $workerId = 5;
        $resolver->setWorker(fn () => $workerId);

        $generator = new FlexIdGenerator(
            workerResolver: $resolver,
        );

        $id = $generator->id();

        // check metadata
        $this::assertSame($workerId, $generator->getWorkerIdFromId($id));
        $this::assertSame(0, $generator->getSequenceFromId($id));
        $this::assertSame($groupId, $generator->getGroupIdFromId($id));

        // check timestamp part monotonicity
        $lastTimestamp = 0;
        $metadataMask = -1 << ($workerBits + $groupBits + $sequenceBits);
        for ($i = 0; $i < 1000; $i++) {
            $id = $generator->id();
            $timestamp = $id & $metadataMask;
            $this::assertSame($timestamp, $generator->getTimestampFromId($id));
            $this::assertGreaterThanOrEqual($lastTimestamp, $timestamp);
            $lastTimestamp = $timestamp;
        }
    }

    public function testGeneratorResolverLag(): void
    {
        $workerBits = 10;
        $groupBits = 12;
        $sequenceBits = 1;
        $groupId = 1;
        $workerTimeoutMs = 10;
        $workerId = 5;

        $resolver = new TestingWorkerResolver(
            workerTimeoutMs: $workerTimeoutMs,
            groupId: $groupId,
            workersBits: $workerBits,
            sequenceBits: $sequenceBits,
            groupsBits: $groupBits,
            useNewWorkerOnSequenceOverflow: false,
        );
        $resolver->setWorker(fn () => $workerId);

        $generator = new FlexIdGenerator(
            workerResolver: $resolver,
        );

        $mask = -1 << ($workerBits + $groupBits + $sequenceBits);
        $id = $generator->id();
        $timestamp1 = $id & $mask;

        $id2 = $generator->id();
        $timestamp2 = $id2 & $mask;

        $this::assertTrue($timestamp2 - $timestamp1 <= $workerTimeoutMs * 1_000_000);
        $resolver->setResolveTime($workerTimeoutMs * 2);

        $id3 = $generator->id();
        $timestamp3 = $id3 & $mask;

        $this::assertNotSame($id2, $id3);
        // timestamp diff must not exceed timeout
        $this::assertSame($timestamp2 + $generator->resolverIdConfiguration->timestepNs, $timestamp3);
    }

    public function testFallbackResolver(): void
    {
        $secondaryWorkerId = 5;
        $secondaryGroupId = 1;
        $groupBits = 1;
        $workerBits = 1;
        $secondaryWorkerBits = 5;
        $secondarySequenceBits = 8;

        $secondaryResolver = new TestingWorkerResolver(
            workerTimeoutMs: 5,
            groupId: $secondaryGroupId,
            workersBits: $secondaryWorkerBits,
            sequenceBits: $secondarySequenceBits,
            groupsBits: $groupBits,
        );

        $secondaryResolver->setWorker(fn () => $secondaryWorkerId);

        $secondaryGenerator = new FlexIdGenerator(
            workerResolver: $secondaryResolver,
        );

        $timeoutMs = 5;
        $sequenceBits = 8;

        $primaryResolver = new TestingWorkerResolver(
            workerTimeoutMs: 5,
            workersBits: $workerBits,
            sequenceBits: $sequenceBits,
            groupsBits: $groupBits,
        );

        $generator = new FlexIdGenerator(
            workerResolver: $primaryResolver,
            fallbackGenerator: $secondaryGenerator,
        );

        $primaryResolver->setWorker(fn () => throw new NoWorkerAvailableException(1, 0, $timeoutMs * 1000));

        // now it should fall back
        $id = $generator->id();
        $this::assertSame($secondaryGroupId, $secondaryGenerator->getGroupIdFromId($id));
        $this::assertSame($secondaryWorkerId, $secondaryGenerator->getWorkerIdFromId($id));

        $primaryResolver->setWorker(fn () => 0);

        \usleep(1_000);
        $id = $generator->id();
        // we still use fallback resolver as timeout is larger
        $this::assertSame($secondaryGroupId, $secondaryGenerator->getGroupIdFromId($id));
        $this::assertSame($secondaryWorkerId, $secondaryGenerator->getWorkerIdFromId($id));

        \usleep($timeoutMs * 1_000);
        $id = $generator->id();
        $workerId = $primaryResolver->getCurrentWorkerId();
        $this::assertNotSame(-1, $workerId);
        $this::assertSame(0, $generator->getGroupIdFromId($id));
    }

    public function testNewWorkerOnSequenceOverflow(): void
    {
        $workerBits = 10;
        $groupBits = 12;
        $sequenceBits = 1;
        $groupId = 1;
        $workerTimeoutMs = 10;
        $initialWorkerId = 5;
        $afterWorkerId = 6;

        $resolver = new TestingWorkerResolver(
            workerTimeoutMs: $workerTimeoutMs,
            groupId: $groupId,
            workersBits: $workerBits,
            sequenceBits: $sequenceBits,
            groupsBits: $groupBits,
            useNewWorkerOnSequenceOverflow: true,
        );

        $resolver->setWorker(fn () => $initialWorkerId);

        $generator = new FlexIdGenerator(
            workerResolver: $resolver,
        );

        // we can generate 2 workers with primary resolver
        $generator->id();
        $id = $generator->id();

        $resolver->setWorker(fn () => $afterWorkerId);
        $id2 = $generator->id();

        $this::assertSame($initialWorkerId, $generator->getWorkerIdFromId($id));
        $this::assertSame($afterWorkerId, $generator->getWorkerIdFromId($id2));
    }

    public function testDatesOperations(): void
    {
        $resolver = new TestingWorkerResolver();
        $resolver->setWorker(fn () => 5);

        $generator = new FlexIdGenerator(workerResolver: $resolver);

        $id = $generator->id();
        $date = $generator->toDate($id);
        $this::assertSame(0, $date->diff(new \DateTime(), true)->s);

        // diff should be below 1ms (1e6 nanoseconds)
        $this::assertTrue(\abs($id - $generator->fromDate($date)) < 1e6);

        // negative numbers should throw exception
        $this->expectException(\Exception::class);
        $generator->toDate(-123456);
    }

    public function testBulkIdGenerate(): void
    {
        $resolver = new TestingWorkerResolver(
            groupId: 3,
            groupsBits: 2,
        );
        $resolver->setWorker(fn () => 5);
        $generator = new FlexIdGenerator(workerResolver: $resolver);

        $total = (1 << $resolver->getConfiguration()->sequenceBits) + 10;
        $ids = $generator->bulkIds($total);

        foreach ($ids as $id) {
            $this::assertSame(5, $generator->getWorkerIdFromId($id));
            $this::assertSame(3, $generator->getGroupIdFromId($id));
        }

        $this::assertCount($total, \array_unique($ids));
    }

    public function testInfo(): void
    {
        $resolver = new TestingWorkerResolver();
        $resolver->setWorker(fn () => 5);
        $generator = new FlexIdGenerator(workerResolver: $resolver);

        $info = $generator->info();
        $this::assertNotEmpty($info);
    }

    public function testGetOffsetFromSnowflake(): void
    {
        $snowflakeBaseDate = '2020-08-01 01:01:01';
        $snowflakeId = (\time() - \strtotime($snowflakeBaseDate)) * 1000 << 22; // 10 bits workers + 12 bits sequence

        $offset = FlexIdGenerator::getOffsetFromSnowflakeId($snowflakeId);

        $resolver = new TestingWorkerResolver();
        $resolver->setWorker(fn () => 5);
        $generator = new FlexIdGenerator(workerResolver: $resolver, timestampOffset: $offset);
        $id = $generator->id();

        $this::assertLessThan(2e9, $id - $snowflakeId); // up to 2sec diff, id always bigger
        $this::assertGreaterThan(0, $id - $snowflakeId);

        $secInFuture = 1000;
        $offset = FlexIdGenerator::getOffsetFromSnowflakeId($snowflakeId, $secInFuture);
        $generator = new FlexIdGenerator(workerResolver: $resolver, timestampOffset: $offset);

        $snowflakeId = ((\time() + $secInFuture) - \strtotime($snowflakeBaseDate)) * 1000 << 22;
        $id2 = $generator->id() + (int) ($secInFuture * 1e9);

        $this::assertLessThan(2e9, $id2 - $snowflakeId); // up to 2sec diff, id always bigger
        $this::assertGreaterThan(0, $id2 - $snowflakeId);
    }

    public function testNegativeTimeOffset(): void
    {
        $resolver = new TestingWorkerResolver();
        $resolver->setWorker(fn () => 5);
        $this->expectException(\DomainException::class);
        new FlexIdGenerator(workerResolver: $resolver, timestampOffset: -1);
    }

    public function testFutureTimeOffset(): void
    {
        $resolver = new TestingWorkerResolver();
        $resolver->setWorker(fn () => 5);
        $generator = new FlexIdGenerator(workerResolver: $resolver, timestampOffset: \time() + 1);
        $this->expectException(\DomainException::class);
        $generator->id();
    }

    public function testIncompatibleWorkersConfiguration(): void
    {
        $resolver = new TestingWorkerResolver(workersBits: 0, useNewWorkerOnSequenceOverflow: true);
        $this->expectException(\DomainException::class);
        new FlexIdGenerator(workerResolver: $resolver);
    }

    public function testTooSmallWorkerTTL(): void
    {
        $resolver = new TestingWorkerResolver(workerTimeoutMs: 1, workersBits: 10, sequenceBits: 10);
        $resolver->setWorker(fn () => 5);
        $this->expectException(\DomainException::class);
        new FlexIdGenerator(workerResolver: $resolver);
    }

    public function testTooBigWorker(): void
    {
        $resolver = new TestingWorkerResolver(workerTimeoutMs: 5, workersBits: 10, sequenceBits: 10);
        $resolver->setWorker(fn () => 5000);
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $this->expectException(\DomainException::class);
        $generator->id();
    }

    public function testNegativeWorker(): void
    {
        $resolver = new TestingWorkerResolver(workerTimeoutMs: 5, workersBits: 10, sequenceBits: 10);
        $resolver->setWorker(fn () => -1);
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $this->expectException(\DomainException::class);
        $generator->id();
    }

    public function testNotAvailableWorker(): void
    {
        $resolver = new TestingWorkerResolver();
        $resolver->setWorker(fn () => throw new NoWorkerAvailableException(1, 0, 0));
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $this->expectException(NoWorkerAvailableException::class);
        $generator->id();
    }

    public function testHandleTimestepDiffAfterMaxSequenceWorker(): void
    {
        $resolver = new TestingWorkerResolver(workerTimeoutMs: 20, workersBits: 20, sequenceBits: 1, groupsBits: 3, dependsOnTimestep: true, resolveTrials: 2);
        $resolver->setWorker(fn () => 1);
        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $id1 = $generator->id();
        $id2 = $generator->id();
        $resolver->setWorker(fn () => 2);
        $resolver->setResolveTime(10);
        $id3 = $generator->id();
        $this::assertSame(1, $generator->getWorkerIdFromId($id1));
        $this::assertSame(2, $generator->getWorkerIdFromId($id3));
        $this::assertNotSame($id1, $id2);
    }

    public function testReleaseWorker(): void
    {
        $resolver = $this->getMockBuilder(TestingWorkerResolver::class)
            ->onlyMethods(['releaseWorker'])
            ->enableOriginalConstructor()
            ->getMock();
        $resolver->setWorker(fn () => 5);

        $generator = new FlexIdGenerator(workerResolver: $resolver);
        $generator->id();

        $resolver->expects($this::any())
            ->method('releaseWorker')
            ->withAnyParameters()
            ->willThrowException(new \Exception());
        $this::assertFalse($generator->releaseWorker());
    }
}
