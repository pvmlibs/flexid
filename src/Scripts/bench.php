<?php

declare(strict_types=1);

use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\CustomSerializer;
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
    serializer: new CustomSerializer(),
    encrypter: new Sparx64Encrypter(
        secret: Sparx64Encrypter::generateSecret(),
        serializer: new BaseSerializer(),
    ),
    signer: new Pvmlibs\FlexId\Signers\Signer(
        secret: Pvmlibs\FlexId\Signers\Signer::generateSecret(),
        serializer: new BaseSerializer(),
        hashAlgo: 'sha256',
    ),
);

echo $bench->presentation(info: in_array('--info', $argv, true), distribution: in_array('--dist', $argv, true)); // @phpstan-ignore variable.undefined, variable.undefined
