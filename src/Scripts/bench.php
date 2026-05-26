<?php

declare(strict_types=1);

use Pvmlibs\FlexId\Encoders\RotatedAlphabetEncoder;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Tools\IdStats;

require_once 'vendor/autoload.php';

$resolver = new StaticWorkerResolver(
    workerHandlerFn: fn () => 0,
    workersBits: 0,
    sequenceBits: 10,
    workerLockFilePath: null,
);
$generator = new FlexIdGenerator(workerResolver: $resolver);

$bench = new IdStats(
    generator: new FlexIdGenerator(workerResolver: $resolver),
    encoder: new RotatedAlphabetEncoder(),
    encrypter: new Sparx64Encrypter(
        secret: Sparx64Encrypter::generateSecret(),
        serializer: new NativeSerializer(),
    ),
    signer: new Pvmlibs\FlexId\Signers\Signer(
        serializer: new NativeSerializer(),
        key: Pvmlibs\FlexId\Signers\Signer::generateKey(),
        hashAlgo: 'tiger128,3',
    ),
);

echo $bench->presentation(info: in_array('--info', $argv, true), distribution: in_array('--dist', $argv, true)); // @phpstan-ignore variable.undefined, variable.undefined
