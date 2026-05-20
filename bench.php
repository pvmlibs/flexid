<?php

use Pvmlibs\FlexId\Encoders\PseudoRandomEncoder;
use Pvmlibs\FlexId\Encrypters\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Tools\IdStats;

require_once 'vendor/autoload.php';

$resolver = new StaticWorkerResolver(
    workerHandlerFn: fn() => 0,
    workersBits: 0,
    sequenceBits: 10,
    workerLockFilePath: null,
);
$generator = new FlexIdGenerator(workerResolver: $resolver);

$bench = new IdStats(
    generator: new FlexIdGenerator(workerResolver: $resolver),
    encoder: new PseudoRandomEncoder(),
    encrypter: new Sparx64Encrypter(
        secret: Sparx64Encrypter::generateSecret(),
        serializer: new NativeSerializer()
    ),
);

echo $bench->presentation(info: in_array('--info', $argv), distribution: in_array('--dist', $argv));





