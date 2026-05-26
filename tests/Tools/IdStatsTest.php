<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Signers\Signer;
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
            encoder: new \Pvmlibs\FlexId\Encoders\RotatedAlphabetEncoder(),
            encrypter: new \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(
                secret: \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter::generateSecret(),
                serializer: new NativeSerializer(),
            ),
            signer: new Signer(new NativeSerializer(), 'PsQBSNyMoz60RpQnSKWBMg=='),
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
            encoder: new \Pvmlibs\FlexId\Encoders\RotatedAlphabetEncoder(),
            encrypter: new \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(
                secret: \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter::generateSecret(),
                serializer: new NativeSerializer(),
            ),
            signer: new Signer(new NativeSerializer(), 'PsQBSNyMoz60RpQnSKWBMg=='),
            yearsDelta: [0, 10, 50, 100, 350],
        );
        $this::assertNotEmpty($idDistribution->presentation());
    }

    public function testIdStatsWrongConfiguration(): void
    {
        $idDistribution = new IdStats(
            generator: new FlexIdGenerator(
                workerResolver: new \Pvmlibs\FlexId\Resolvers\ApcuTimestepWorkerResolver(
                    workersBits: 10,
                    sequenceBits: 10,
                    groupsBits: 10,
                    timestampBitshift: 0,
                ),
            ),
            encoder: new \Pvmlibs\FlexId\Encoders\RotatedAlphabetEncoder(),
            encrypter: new \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(
                secret: \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter::generateSecret(),
                serializer: new NativeSerializer(),
            ),
            signer: new Signer(new NativeSerializer(), 'PsQBSNyMoz60RpQnSKWBMg=='),
        );
        $this::assertNotEmpty($idDistribution->presentation());
    }
}
