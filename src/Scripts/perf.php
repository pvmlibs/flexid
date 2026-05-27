<?php

declare(strict_types=1);

use Pvmlibs\FlexId\Encoders\FixedLengthEncoder;
use Pvmlibs\FlexId\Encoders\RotatedAlphabetEncoder;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Serializers\FixedLengthSerializer;
use Pvmlibs\FlexId\Serializers\GMPSerializer;
use Pvmlibs\FlexId\Serializers\NativeSerializer;

require_once 'vendor/autoload.php';

$resolver = new StaticWorkerResolver(
    workerHandlerFn: fn () => 0,
    workersBits: 0,
    sequenceBits: 10,
    workerLockFilePath: null,
);
$generator = new FlexIdGenerator(workerResolver: $resolver);

$encrypter = new Sparx64Encrypter(
    secret: 'rCl29//aZ51LjLQZKUbMUA==',
    serializer: new NativeSerializer(),
);

$nativeSerializer = new NativeSerializer();
if (extension_loaded('bcmath')) {
    $BCMathSerializer = new BCMathSerializer();
}
if (extension_loaded('gmp')) {
    $GMPSerializer = new GMPSerializer();
}
$fixedLengthSerializer = new FixedLengthSerializer();

$rotatedAlphabetEncoder = new RotatedAlphabetEncoder();
$fixedLengthEncoder = new FixedLengthEncoder();

$signer = new Pvmlibs\FlexId\Signers\Signer(
    serializer: new NativeSerializer(),
    key: 'rCl29//aZ51LjLQZKUbMUA==',
    hashAlgo: 'tiger128,3', // tiger is always available
);

file_put_contents(__DIR__ . '/RefPerf/perf.txt', '');

$total = 10000;
// range 0-0xFFFF better representing 64bit input data for serializers
$serializerRands = new SplFixedArray($total);
for ($i = 0; $i < $total; $i++) {
    $serializerRands[$i] = random_int(0, 0xFFFF);
}

// liner numbers across whole range
$fullRangeRands = new SplFixedArray($total);
$step = \intdiv(PHP_INT_MAX, $total);
$index = 0;
for ($i = $step; $i < PHP_INT_MAX; $i += $step) {
    $fullRangeRands[$index++] = random_int(0, PHP_INT_MAX);
}

/**
 * @param Closure(int $index): (int|string|array<int>) $closure
 *
 * @return SplFixedArray<int|string>
 */
function bench(Closure $closure, string $className, string $note = ''): SplFixedArray
{
    global $total;
    $ids = new SplFixedArray($total);
    $class = explode('\\', $className);

    $start = hrtime(true);

    for ($i = 0; $i < $total; $i++) {
        $ids[$i] = $closure($i);
    }

    $end = hrtime(true);
    $output = str_pad(end($class) . " {$note}", 35) . number_format($total / (($end - $start) / 1e9)) . " ops/s\n";
    echo $output;
    file_put_contents(__DIR__ . '/RefPerf/perf.txt', $output, FILE_APPEND);

    return $ids;
}

bench(fn () => $generator->id(), $generator::class);

// encoders
$encodedIds = bench(fn (int $index) => $rotatedAlphabetEncoder->encode((int) $fullRangeRands[$index]), $rotatedAlphabetEncoder::class, 'encode');
bench(fn (int $index) => $rotatedAlphabetEncoder->decode((string) $encodedIds[$index]), $rotatedAlphabetEncoder::class, 'decode');

$encodedIds = bench(fn (int $index) => $fixedLengthEncoder->encode((int) $fullRangeRands[$index]), $fixedLengthEncoder::class, 'encode');
bench(fn (int $index) => $fixedLengthEncoder->decode((string) $encodedIds[$index]), $fixedLengthEncoder::class, 'decode');

$mask = ((1 << 16) - 1);
// serializers
$serializedIds = bench(fn (int $index) => $nativeSerializer->serialize([
    $serializerRands[$index], $serializerRands[$index], $serializerRands[$index], $serializerRands[$index],
]), $nativeSerializer::class, 'serialize');
bench(fn (int $index) => $nativeSerializer->deserialize((string) $serializedIds[$index]), $nativeSerializer::class, 'deserialize');

if (extension_loaded('bcmath')) {
    $serializedIds = bench(fn (int $index) => $BCMathSerializer->serialize([
        $serializerRands[$index], $serializerRands[$index], $serializerRands[$index], $serializerRands[$index],
    ]), $BCMathSerializer::class, 'serialize');
    bench(fn (int $index) => $BCMathSerializer->deserialize((string) $serializedIds[$index]), $BCMathSerializer::class, 'deserialize');
}

if (extension_loaded('gmp')) {
    $serializedIds = bench(fn (int $index) => $GMPSerializer->serialize([
        $serializerRands[$index], $serializerRands[$index], $serializerRands[$index], $serializerRands[$index],
    ]), $GMPSerializer::class, 'serialize');
    bench(fn (int $index) => $GMPSerializer->deserialize((string) $serializedIds[$index]), $GMPSerializer::class, 'deserialize');
}

$serializedIds = bench(fn (int $index) => $fixedLengthSerializer->serialize([
    $serializerRands[$index], $serializerRands[$index], $serializerRands[$index], $serializerRands[$index],
]), $fixedLengthSerializer::class, 'serialize');
bench(fn (int $index) => $fixedLengthSerializer->deserialize((string) $serializedIds[$index]), $fixedLengthSerializer::class, 'deserialize');

// encrypters
$encryptedIds = bench(fn (int $index) => $encrypter->encrypt((int) $fullRangeRands[$index]), $encrypter::class, 'encrypt');
bench(fn (int $index) => $encrypter->decrypt((string) $encryptedIds[$index]), $encrypter::class, 'decrypt');

// signers
$signedIds = bench(fn (int $index) => $signer->getSignedId((string) $serializerRands[$index]), $signer::class, 'sign');
bench(fn (int $index) => $signer->getIdFromSigned((string) $signedIds[$index]), $signer::class, 'verify');
