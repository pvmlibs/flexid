<?php

declare(strict_types=1);

use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\CustomSerializer;
use Pvmlibs\FlexId\Tools\IdStats;
use Pvmlibs\FlexId\Tools\KeyGen;

require_once 'vendor/autoload.php';

$resolver = new StaticWorkerResolver(
    workerHandlerFn: fn () => 0,
    workersBits: 0,
    sequenceBits: 10,
    workerLockFilePath: null,
);

$bench = new IdStats(
    generator: new FlexIdGenerator(workerResolver: $resolver),
    serializer: new CustomSerializer(),
    encrypter: new Sparx64Encrypter(
        base64secret: KeyGen::generate(),
        serializer: new BaseSerializer(),
    ),
    signer: new Pvmlibs\FlexId\Signers\Signer(
        serializer: new BaseSerializer(),
        hmac: new Pvmlibs\FlexId\HMAC\Sha256HMAC(KeyGen::generate()),
    ),
);

echo $bench->presentation(info: in_array('--info', $argv, true), distribution: in_array('--dist', $argv, true)); // @phpstan-ignore variable.undefined, variable.undefined
