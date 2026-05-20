<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Exceptions\IdConfigurationException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Tools\IdStats;

/**
 * @internal
 */
final class IdStatsTest extends TestCase
{
    public function testIdStatsStaticResolver(): void
    {
        $idDistribution = new IdStats(
            generator: new FlexIdGenerator(
                workerResolver: new \Pvmlibs\FlexId\Resolvers\StaticWorkerResolver(
                    workerHandlerFn: fn () => 0,
                    workersBits: 0,
                    sequenceBits: 10,
                    timestampBitshift: 0,
                ),
            ),
            encoder: new \Pvmlibs\FlexId\Encoders\PseudoRandomEncoder(),
            encrypter: new \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(
                secret: \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter::generateSecret(),
                serializer: new \Pvmlibs\FlexId\Encrypters\Serializers\NativeSerializer(),
            ),
            yearsDelta: [0, 10, 50, 100, 350],
        );
        $this::assertNotEmpty($idDistribution->presentation());
    }

    public function testIdStatsApcuResolver(): void
    {
        $idDistribution = new IdStats(
            generator: new FlexIdGenerator(
                workerResolver: new \Pvmlibs\FlexId\Resolvers\ApcuTimestepWorkerResolver(
                    workersBits: 0,
                    sequenceBits: 10,
                    useNewWorkerOnSequenceOverflow: false,
                    timestampBitshift: 0,
                ),
            ),
            encoder: new \Pvmlibs\FlexId\Encoders\PseudoRandomEncoder(),
            encrypter: new \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(
                secret: \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter::generateSecret(),
                serializer: new \Pvmlibs\FlexId\Encrypters\Serializers\NativeSerializer(),
            ),
            yearsDelta: [0, 10, 50, 100, 350],
        );
        $this::assertNotEmpty($idDistribution->presentation());
    }

    public function testIdStatsWrongConfiguration(): void
    {
        $this->expectException(IdConfigurationException::class);
        $idDistribution = new IdStats(
            generator: new FlexIdGenerator(
                workerResolver: new \Pvmlibs\FlexId\Resolvers\ApcuTimestepWorkerResolver(
                    workersBits: 10,
                    sequenceBits: 10,
                    timestampBitshift: 10,
                ),
            ),
            encoder: new \Pvmlibs\FlexId\Encoders\PseudoRandomEncoder(),
            encrypter: new \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(
                secret: \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter::generateSecret(),
                serializer: new \Pvmlibs\FlexId\Encrypters\Serializers\NativeSerializer(),
            ),
        );
        $this::assertNotEmpty($idDistribution->presentation());
    }
}
