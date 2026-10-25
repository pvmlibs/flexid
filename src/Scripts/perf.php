<?php

declare(strict_types=1);

use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Serializers\Base64Serializer;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Serializers\CustomSerializer;
use Pvmlibs\FlexId\Serializers\HashSerializer;
use Pvmlibs\FlexId\Serializers\HexSerializer;
use Pvmlibs\FlexId\Tools\Alphabets;

require_once 'vendor/autoload.php';

$resolverStatic = new StaticWorkerResolver(
    workerHandlerFn: fn () => 0,
    workersBits: 0,
    sequenceBits: 10,
    workerLockFilePath: null,
);
$resolverRandom = new Pvmlibs\FlexId\Resolvers\RandomWorkerResolver();
$generatorStatic = new FlexIdGenerator(workerResolver: $resolverStatic);
$generatorRandom = new FlexIdGenerator(workerResolver: $resolverRandom);

$baseSerializer = new BaseSerializer(Alphabets::BASE64_ALPHABET);
$hexSerializer = new HexSerializer();
$base64Serializer = new Base64Serializer();
$hashSerializer = new HashSerializer();
$customSerializer = new CustomSerializer();

if (extension_loaded('bcmath')) {
    $customSerializer = new CustomSerializer(new Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersBCMath());
} elseif (extension_loaded('gmp')) {
    $customSerializer = new CustomSerializer(new Pvmlibs\FlexId\Serializers\IntegerOperations\FullRangeIntegersGmp());
}

$key32bit = Pvmlibs\FlexId\Tools\KeyGen::generate();

$reportFile = __DIR__ . '/RefPerf/perf.txt';

if (extension_loaded('sodium')) {
    $XChaCha20Encrypter = new Pvmlibs\FlexId\Encrypters\XChaCha20Encrypter(
        base64secret: $key32bit,
        base64Encode: false,
    );
    $defHashAlgo = 'siphash-2-4';
    $hmac = new Pvmlibs\FlexId\HMAC\SiphashHMAC($key32bit);
} else {
    $defHashAlgo = 'sha256';
    $hmac = new Pvmlibs\FlexId\HMAC\Sha256HMAC($key32bit);
}

$encrypterSparx64 = new Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(
    base64secret: $key32bit,
    serializer: $baseSerializer,
);

$encrypterSparx128 = new Pvmlibs\FlexId\Encrypters\Sparx128Encrypter(
    base64secret: $key32bit,
    hmac: $hmac,
    serializer: $baseSerializer,
);

$total = 10000;
// range 0-0xFFFF better represents 64bit input data for serializers
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

$signer = new Pvmlibs\FlexId\Signers\Signer(
    serializer: new BaseSerializer(),
    hmac: $hmac,
);

if (extension_loaded('openssl')) {
    $aesEncrypter = new Pvmlibs\FlexId\Encrypters\AesEncrypter(
        base64secret: $key32bit,
        hmac: $hmac,
        serializer: new BaseSerializer(),
    );
}

if (function_exists('opcache_get_status')) {
    $opcache = @opcache_get_status();
    if ($opcache === false) {
        $isOpcache = false;
    } else {
        $isOpcache = (bool) $opcache['opcache_enabled'];
    }
} else {
    $isOpcache = false;
}
$extensions = [];
$extNames = ['sodium', 'bcmath', 'gmp', 'openssl', 'flexid_utils'];
foreach ($extNames as $extName) {
    if (extension_loaded($extName)) {
        $extensions[] = $extName;
    }
}
$jit = (bool) ini_get('opcache.jit');
$resultOutput = sprintf(
    'PHP: %s, opcache: %s, jit: %s, Extensions: %s',
    PHP_VERSION_ID,
    $isOpcache ? 'ON' : 'OFF',
    ($isOpcache && $jit) ? 'ON' : 'OFF',
    implode(', ', $extensions),
) . "\n";
echo "\n" . $resultOutput;

// do short tests first to include warm up latency
/**
 * @param Closure(int $index): (int|string|array<int>) $closure
 *
 * @return SplFixedArray<int|string>
 */
function bench_100ops(Closure $closure, string $className, string $note = ''): SplFixedArray
{
    global $total, $resultOutput;
    $ids = new SplFixedArray($total);
    $class = explode('\\', $className);

    $start = hrtime(true);

    for ($i = 0; $i < 100; $i++) {
        $ids[$i] = $closure($i);
    }

    $end = hrtime(true);
    $output = str_pad(end($class) . " {$note}", 60) . number_format(($end - $start) / 1e6, 3) . " ms\n";
    echo $output;
    $resultOutput .= $output;

    return $ids;
}
$encodeSection = "\nEncode 100 ids\n";
echo $encodeSection;
$resultOutput .= $encodeSection;

if (extension_loaded('bcmath') || extension_loaded('gmp')) {
    bench_100ops(fn (int $index) => $customSerializer->serialize((int) $positivesRands[$index]), $customSerializer::class, 'encode');
}
bench_100ops(fn (int $index) => $baseSerializer->serialize((int) $positivesRands[$index]), $baseSerializer::class, 'encode');
bench_100ops(fn (int $index) => $hashSerializer->serialize((int) $positivesRands[$index]), $hashSerializer::class, 'encode');
bench_100ops(fn (int $index) => $hexSerializer->serialize((int) $positivesRands[$index]), $hexSerializer::class, 'encode');
bench_100ops(fn (int $index) => $base64Serializer->serialize((int) $positivesRands[$index]), $base64Serializer::class, 'encode');

$encryptersSection = "\nEncrypt + sign 100 ids\n";
echo $encryptersSection;
$resultOutput .= $encryptersSection;

bench_100ops(fn (int $index) => $signer->getSignedId($encrypterSparx64->encrypt((int) $fullRangeRands[$index])), $encrypterSparx64::class, "128-bit key, 64-bit sign {$defHashAlgo}");
bench_100ops(fn (int $index) => $encrypterSparx128->encrypt((int) $fullRangeRands[$index]), $encrypterSparx128::class, "256-bit key, 64-bit sign {$defHashAlgo}");
if (extension_loaded('flexid_utils')) {
    $encrypterSparx128Turbo = new Pvmlibs\FlexId\Encrypters\Sparx128TurboEncrypter($key32bit);
    bench_100ops(fn (int $index) => $encrypterSparx128Turbo->encrypt((int) $fullRangeRands[$index]), $encrypterSparx128Turbo::class, '256-bit key, 64-bit sign siphash-2-4');
}
if (extension_loaded('openssl')) {
    bench_100ops(fn (int $index) => $aesEncrypter->encrypt((int) $fullRangeRands[$index]), $aesEncrypter::class, "256-bit key, 64-bit sign {$defHashAlgo}");
}
if (extension_loaded('sodium')) {
    bench_100ops(fn (int $index) => $XChaCha20Encrypter->encrypt((int) $fullRangeRands[$index]), $XChaCha20Encrypter::class, '256-bit key, 128-bit sign Poly1305');
}
$batchSection = "\nTest with 10k operations:\n";
echo $batchSection;
$resultOutput .= $batchSection;

/**
 * @param Closure(int $index): (int|string|array<int>) $closure
 *
 * @return SplFixedArray<int|string>
 */
function bench(Closure $closure, string $className, string $note = ''): SplFixedArray
{
    global $total, $resultOutput;
    $ids = new SplFixedArray($total);
    $class = explode('\\', $className);

    $start = hrtime(true);

    for ($i = 0; $i < $total; $i++) {
        $ids[$i] = $closure($i);
    }

    $end = hrtime(true);
    $output = str_pad(end($class) . " {$note}", 50) . number_format($total / (($end - $start) / 1e9)) . " ops/s\n";
    echo $output;
    $resultOutput .= $output;

    return $ids;
}

bench(fn () => $generatorStatic->id(), $generatorStatic::class, 'with StaticWorkerResolver');
bench(fn () => $generatorRandom->id(), $generatorRandom::class, 'with RandomWorkerResolver');

// serializers
$serializedIds = bench(fn (int $index) => $baseSerializer->serialize($fullRangeRands[$index]), $baseSerializer::class, 'serialize');
bench(fn (int $index) => $baseSerializer->deserialize((string) $serializedIds[$index]), $baseSerializer::class, 'deserialize');

if (extension_loaded('bcmath') || extension_loaded('gmp')) {
    $serializedIds = bench(fn (int $index) => $customSerializer->serialize($fullRangeRands[$index]), $customSerializer::class, 'serialize');
    bench(fn (int $index) => $customSerializer->deserialize((string) $serializedIds[$index]), $customSerializer::class, 'deserialize');
}

$serializedIds = bench(fn (int $index) => $hashSerializer->serialize($positivesRands[$index]), $hashSerializer::class, 'serialize');
bench(fn (int $index) => $hashSerializer->deserialize((string) $serializedIds[$index]), $hashSerializer::class, 'deserialize');

$serializedIds = bench(fn (int $index) => $hexSerializer->serialize($fullRangeRands[$index]), $hexSerializer::class, 'serialize');
bench(fn (int $index) => $hexSerializer->deserialize((string) $serializedIds[$index]), $hexSerializer::class, 'deserialize');

$serializedIds = bench(fn (int $index) => $base64Serializer->serialize($fullRangeRands[$index]), $base64Serializer::class, 'serialize');
bench(fn (int $index) => $base64Serializer->deserialize((string) $serializedIds[$index]), $base64Serializer::class, 'deserialize');

// encrypters
$encryptedIds = bench(fn (int $index) => $encrypterSparx64->encrypt((int) $fullRangeRands[$index]), $encrypterSparx64::class, 'encrypt');
bench(fn (int $index) => $encrypterSparx64->decrypt((string) $encryptedIds[$index]), $encrypterSparx64::class, 'decrypt');

$encryptedIds = bench(fn (int $index) => $signer->getSignedId($encrypterSparx64->encrypt((int) $fullRangeRands[$index])), $encrypterSparx64::class, "encrypt (+sign {$defHashAlgo})");
bench(fn (int $index) => $encrypterSparx64->decrypt($signer->getIdFromSigned((string) $encryptedIds[$index])), $encrypterSparx64::class, "decrypt (+sign {$defHashAlgo})");

$encryptedIds = bench(fn (int $index) => $encrypterSparx128->encrypt((int) $fullRangeRands[$index]), $encrypterSparx128::class, "encrypt (+sign {$defHashAlgo})");
bench(fn (int $index) => $encrypterSparx128->decrypt((string) $encryptedIds[$index]), $encrypterSparx128::class, "decrypt (+sign {$defHashAlgo})");

if (extension_loaded('flexid_utils')) {
    $encrypterSparx128Turbo = new Pvmlibs\FlexId\Encrypters\Sparx128TurboEncrypter($key32bit);
    $encryptedIds = bench(fn (int $index) => $encrypterSparx128Turbo->encrypt((int) $fullRangeRands[$index]), $encrypterSparx128Turbo::class, 'encrypt (+sign siphash-2-4)');
    bench(fn (int $index) => $encrypterSparx128Turbo->decrypt((string) $encryptedIds[$index]), $encrypterSparx128Turbo::class, 'decrypt (+sign siphash-2-4)');
}

if (extension_loaded('openssl')) {
    $encryptedIds = bench(fn (int $index) => $aesEncrypter->encrypt((int) $fullRangeRands[$index]), $aesEncrypter::class, "encrypt (+sign {$defHashAlgo})");
    bench(fn (int $index) => $aesEncrypter->decrypt((string) $encryptedIds[$index]), $aesEncrypter::class, "decrypt (+sign {$defHashAlgo})");
}

if (extension_loaded('sodium')) {
    $encryptedIds = bench(fn (int $index) => $XChaCha20Encrypter->encrypt((int) $fullRangeRands[$index]), $XChaCha20Encrypter::class, 'encrypt (+sign Poly1305)');
    bench(fn (int $index) => $XChaCha20Encrypter->decrypt((string) $encryptedIds[$index]), $XChaCha20Encrypter::class, 'decrypt (+sign Poly1305)');
}

// signers
$signedIds = bench(fn (int $index) => $signer->getSignedId((string) $fullRangeRands[$index]), $signer::class, "(BaseSerializer) sign {$defHashAlgo}");
bench(fn (int $index) => $signer->getIdFromSigned((string) $signedIds[$index]), $signer::class, "(BaseSerializer) verify {$defHashAlgo}");

// hmacs
if (extension_loaded('sodium') || extension_loaded('flexid_utils')) {
    $hmac = new Pvmlibs\FlexId\HMAC\SiphashHMAC($key32bit);
    bench(fn (int $index) => $hmac->sign('some sign message', $fullRangeRands[$index]), $hmac::class, 'sign');
}
if (extension_loaded('flexid_utils')) {
    $hmac = new Pvmlibs\FlexId\HMAC\Blake3HMACFlexIdExt($key32bit);
    bench(fn (int $index) => $hmac->sign('some sign message', $fullRangeRands[$index]), $hmac::class, 'sign');
}
if (extension_loaded('sodium')) {
    $hmac = new Pvmlibs\FlexId\HMAC\Blake2HMACSodiumExt($key32bit);
    bench(fn (int $index) => $hmac->sign('some sign message', $fullRangeRands[$index]), $hmac::class, 'sign');
}
$hmac = new Pvmlibs\FlexId\HMAC\Sha256HMAC($key32bit);
bench(fn (int $index) => $hmac->sign('some sign message', $fullRangeRands[$index]), $hmac::class, 'sign');

file_put_contents($reportFile, $resultOutput, FILE_APPEND);
