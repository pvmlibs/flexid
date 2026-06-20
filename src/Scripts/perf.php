<?php

declare(strict_types=1);

use Pvmlibs\FlexId\Encrypters\AesEncrypter;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\Encrypters\XChaCha20Encrypter;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\RandomWorkerResolver;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Serializers\Base64Serializer;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\CustomSerializer;
use Pvmlibs\FlexId\Serializers\HashSerializer;
use Pvmlibs\FlexId\Serializers\HexSerializer;

require_once 'vendor/autoload.php';

$resolverStatic = new StaticWorkerResolver(
    workerHandlerFn: fn () => 0,
    workersBits: 0,
    sequenceBits: 10,
    workerLockFilePath: null,
);
$resolverRandom = new RandomWorkerResolver();
$generatorStatic = new FlexIdGenerator(workerResolver: $resolverStatic);
$generatorRandom = new FlexIdGenerator(workerResolver: $resolverRandom);

$encrypterSparx = new Sparx64Encrypter(
    secret: 'rCl29//aZ51LjLQZKUbMUA==',
    serializer: new BaseSerializer(),
);

$baseSerializer = new BaseSerializer();
$hexSerializer = new HexSerializer();
$base64Serializer = new Base64Serializer();
$hashSerializer = new HashSerializer();
$customSerializerPositives = new CustomSerializer();
if (extension_loaded('bcmath')) {
    $customSerializerBCMath = new CustomSerializer(new Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersBCMath());
}
if (extension_loaded('gmp')) {
    $customSerializerGMP = new CustomSerializer(new Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersGmp());
}

$signer = new Pvmlibs\FlexId\Signers\Signer(
    serializer: new BaseSerializer(),
    secret: 'rCl29//aZ51LjLQZKUbMUA==',
);

$aes = new AesEncrypter(
    secret: AesEncrypter::generateSecret(),
    serializer: new BaseSerializer(),
);

$reportFile = __DIR__ . '/RefPerf/perf.txt';

if (extension_loaded('sodium')) {
    $fastSigner = new Pvmlibs\FlexId\Signers\Signer(
        serializer: new HexSerializer(),
        secret: 'rCl29//aZ51LjLQZKUbMUA==',
        hashAlgo: 'siphash-2-4',
    );
    $encrypterChaCha = new XChaCha20Encrypter(
        secret: '3khTuzik9M4LpRSrl1+Lk46OSA5TR2oJhSy3lV+6RS0=',
    );
}

$header = sprintf('PHP: %s', PHP_VERSION_ID) . "\nTest with 10k operations:\n";
echo $header;
file_put_contents($reportFile, $header);

$total = 10000;
// range 0-0xFFFF better representing 64bit input data for serializers
$positivesRands = new SplFixedArray($total);
for ($i = 0; $i < $total; $i++) {
    $positivesRands[$i] = random_int(0, PHP_INT_MAX);
}

// liner numbers across whole range
$fullRangeRands = new SplFixedArray($total);
$step = \intdiv(PHP_INT_MAX, $total);
$index = 0;
for ($i = $step; $i < PHP_INT_MAX; $i += $step) {
    $fullRangeRands[$index++] = random_int(PHP_INT_MIN, PHP_INT_MAX);
}

/**
 * @param Closure(int $index): (int|string|array<int>) $closure
 *
 * @return SplFixedArray<int|string>
 */
function bench(Closure $closure, string $className, string $note = ''): SplFixedArray
{
    global $total, $reportFile;
    $ids = new SplFixedArray($total);
    $class = explode('\\', $className);

    $start = hrtime(true);

    for ($i = 0; $i < $total; $i++) {
        $ids[$i] = $closure($i);
    }

    $end = hrtime(true);
    $output = str_pad(end($class) . " {$note}", 50) . number_format($total / (($end - $start) / 1e9)) . " ops/s\n";
    echo $output;
    file_put_contents($reportFile, $output, FILE_APPEND);

    return $ids;
}

bench(fn () => $generatorStatic->id(), $generatorStatic::class, 'with StaticWorkerResolver');
bench(fn () => $generatorRandom->id(), $generatorRandom::class, 'with RandomWorkerResolver');

// serializers
$serializedIds = bench(fn (int $index) => $baseSerializer->serialize($fullRangeRands[$index]), $baseSerializer::class, 'serialize');
bench(fn (int $index) => $baseSerializer->deserialize((string) $serializedIds[$index]), $baseSerializer::class, 'deserialize');

$serializedIds = bench(fn (int $index) => $customSerializerPositives->serialize($positivesRands[$index]), $customSerializerPositives::class, '(positive int) serialize');
bench(fn (int $index) => $customSerializerPositives->deserialize((string) $serializedIds[$index]), $customSerializerPositives::class, '(positive int) deserialize');

if (extension_loaded('bcmath')) {
    $serializedIds = bench(fn (int $index) => $customSerializerBCMath->serialize($fullRangeRands[$index]), $customSerializerBCMath::class, '(full range bcmath) serialize');
    bench(fn (int $index) => $customSerializerBCMath->deserialize((string) $serializedIds[$index]), $customSerializerBCMath::class, '(full range bcmath) deserialize');
}

if (extension_loaded('gmp')) {
    $serializedIds = bench(fn (int $index) => $customSerializerGMP->serialize($fullRangeRands[$index]), $customSerializerGMP::class, '(full range gmp) serialize');
    bench(fn (int $index) => $customSerializerGMP->deserialize((string) $serializedIds[$index]), $customSerializerGMP::class, '(full range gmp) deserialize');
}

$serializedIds = bench(fn (int $index) => $hashSerializer->serialize($positivesRands[$index]), $hashSerializer::class, 'serialize');
bench(fn (int $index) => $hashSerializer->deserialize((string) $serializedIds[$index]), $hashSerializer::class, 'deserialize');

$serializedIds = bench(fn (int $index) => $hexSerializer->serialize($fullRangeRands[$index]), $hexSerializer::class, 'serialize');
bench(fn (int $index) => $hexSerializer->deserialize((string) $serializedIds[$index]), $hexSerializer::class, 'deserialize');

$serializedIds = bench(fn (int $index) => $base64Serializer->serialize($fullRangeRands[$index]), $base64Serializer::class, 'serialize');
bench(fn (int $index) => $base64Serializer->deserialize((string) $serializedIds[$index]), $base64Serializer::class, 'deserialize');

// encrypters
$encryptedIds = bench(fn (int $index) => $encrypterSparx->encrypt((int) $fullRangeRands[$index]), $encrypterSparx::class, 'encrypt');
bench(fn (int $index) => $encrypterSparx->decrypt((string) $encryptedIds[$index]), $encrypterSparx::class, 'decrypt');

if (extension_loaded('openssl')) {
    $encryptedIds = bench(fn (int $index) => $aes->encrypt((int) $fullRangeRands[$index]), $aes::class, 'encrypt');
    bench(fn (int $index) => $aes->decrypt((string) $encryptedIds[$index]), $aes::class, 'decrypt');
}

if (extension_loaded('sodium')) {
    $encryptedIds = bench(fn (int $index) => $encrypterChaCha->encrypt((int) $fullRangeRands[$index]), $encrypterChaCha::class, 'encrypt');
    bench(fn (int $index) => $encrypterChaCha->decrypt((string) $encryptedIds[$index]), $encrypterChaCha::class, 'decrypt');

    // fast option signer
    $signedIds = bench(fn (int $index) => $fastSigner->getSignedId((string) $fullRangeRands[$index]), $fastSigner::class, '(siphash + HexSerializer) sign');
    bench(fn (int $index) => $fastSigner->getIdFromSigned((string) $signedIds[$index]), $fastSigner::class, '(siphash + HexSerializer) verify');
}

// signers
$signedIds = bench(fn (int $index) => $signer->getSignedId((string) $fullRangeRands[$index]), $signer::class, '(sha256 + BaseSerializer) sign');
bench(fn (int $index) => $signer->getIdFromSigned((string) $signedIds[$index]), $signer::class, '(sha256 + BaseSerializer) verify');

$signer = new Pvmlibs\FlexId\Signers\Signer(
    serializer: new BaseSerializer(),
    secret: 'rCl29//aZ51LjLQZKUbMUA==',
);

// encrypting with signing
$sparxEncrypter = new Sparx64Encrypter(
    secret: 'rCl29//aZ51LjLQZKUbMUA==',
    serializer: new BaseSerializer(),
);

$aesEncrypter = new AesEncrypter(
    secret: 'HtPA2DA8cy2gRUC4h+tKnKIjUt5xuLJzkmKc3MtwZpc=',
    serializer: new BaseSerializer(),
);

$XChaCha20Encrypter = new XChaCha20Encrypter(
    secret: 'HtPA2DA8cy2gRUC4h+tKnKIjUt5xuLJzkmKc3MtwZpc=',
);

/**
 * @param Closure(int $index): (int|string|array<int>) $closure
 *
 * @return SplFixedArray<int|string>
 */
function bench_100ops(Closure $closure, string $className, string $note = ''): SplFixedArray
{
    global $total, $reportFile;
    $ids = new SplFixedArray($total);
    $class = explode('\\', $className);

    $start = hrtime(true);

    for ($i = 0; $i < 100; $i++) {
        $ids[$i] = $closure($i);
    }

    $end = hrtime(true);
    $output = str_pad(end($class) . " {$note}", 45) . number_format(($end - $start) / 1e6, 3) . " ms\n";
    echo $output;
    file_put_contents($reportFile, $output, FILE_APPEND);

    return $ids;
}
$encodeSection = "\nEncode 100 ids\n";
echo $encodeSection;
file_put_contents($reportFile, $encodeSection, FILE_APPEND);

bench_100ops(fn (int $index) => $customSerializerPositives->serialize((int) $positivesRands[$index]), $customSerializerPositives::class, 'encode');
bench_100ops(fn (int $index) => $baseSerializer->serialize((int) $positivesRands[$index]), $baseSerializer::class, 'encode');
bench_100ops(fn (int $index) => $hashSerializer->serialize((int) $positivesRands[$index]), $hashSerializer::class, 'encode');
bench_100ops(fn (int $index) => $hexSerializer->serialize((int) $positivesRands[$index]), $hexSerializer::class, 'encode');
bench_100ops(fn (int $index) => $base64Serializer->serialize((int) $positivesRands[$index]), $base64Serializer::class, 'encode');

$encryptersSection = "\nEncrypt + sign 100 ids\n";
echo $encryptersSection;
file_put_contents($reportFile, $encryptersSection, FILE_APPEND);

bench_100ops(fn (int $index) => $signer->getSignedId($sparxEncrypter->encrypt((int) $fullRangeRands[$index])), $sparxEncrypter::class, '128-bit key, 64-bit sign');
bench_100ops(fn (int $index) => $aesEncrypter->encrypt((int) $fullRangeRands[$index]), $aesEncrypter::class, '256-bit key, 64-bit sign');
bench_100ops(fn (int $index) => $XChaCha20Encrypter->encrypt((int) $fullRangeRands[$index]), $XChaCha20Encrypter::class, '256-bit key, 128-bit sign');
