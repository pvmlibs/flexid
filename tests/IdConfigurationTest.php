<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdConfigurationException;
use Pvmlibs\FlexId\VO\IdConfiguration;

/**
 * @covers \Pvmlibs\FlexId\VO\IdConfiguration
 *
 * @internal
 */
final class IdConfigurationTest extends TestCase
{
    public function testNegativeBits(): void
    {
        $this->expectException(IdConfigurationException::class);
        new IdConfiguration(
            workersBits: -1,
            sequenceBits: 0,
            groupsBits: 0,
            groupId: 0,
            useNewWorkerOnSequenceOverflow: false,
        );
    }

    public function testBigWorkersBits(): void
    {
        $this->expectException(IdConfigurationException::class);
        new IdConfiguration(
            workersBits: 22,
            sequenceBits: 0,
            groupsBits: 0,
            groupId: 0,
            useNewWorkerOnSequenceOverflow: false,
        );
    }

    public function testGroupsBits(): void
    {
        $this->expectException(IdConfigurationException::class);
        new IdConfiguration(
            workersBits: 0,
            sequenceBits: 0,
            groupsBits: 0,
            groupId: 1,
            useNewWorkerOnSequenceOverflow: false,
        );
    }

    public function testTotalBits(): void
    {
        $this->expectException(IdConfigurationException::class);
        new IdConfiguration(
            workersBits: 10,
            sequenceBits: 10,
            groupsBits: 10,
            groupId: 0,
            useNewWorkerOnSequenceOverflow: false,
        );
    }

    public function testValidConfiguration(): void
    {
        $config = new IdConfiguration(
            workersBits: 8,
            sequenceBits: 8,
            groupsBits: 2,
            groupId: 3,
            useNewWorkerOnSequenceOverflow: false,
        );
        $this::assertSame(1 << (8 + 8 + 2), $config->timestepNs);
        $this::assertSame(1 << 8, $config->maxWorkers);
        $this::assertSame(1 << 8, $config->maxSequence);
        $this::assertSame(8 + 8 + 2, $config->totalMetaDataBits);
    }
}
