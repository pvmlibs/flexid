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

if (extension_loaded('sodium')) {
    $algo = 'siphash-2-4';
} else {
    $algo = 'tiger128,3';
}

$signer = new Pvmlibs\FlexId\Signers\Signer(
    serializer: new NativeSerializer(),
    key: 'rCl29//aZ51LjLQZKUbMUA==',
    hashAlgo: $algo,
    maxSignLength: 13,
);

file_put_contents(__DIR__ . '/RefPerf/perf.txt', '');

/**
 * @param Closure(int $index): (int|string|array<int>) $closure
 *
 * @return SplFixedArray<int|string>
 */
function bench(Closure $closure, string $className, string $note = ''): SplFixedArray
{
    $total = 10000;
    $ids = new SplFixedArray($total + 1);
    $step = \intdiv(PHP_INT_MAX, $total);
    $class = explode('\\', $className);

    $start = hrtime(true);
    $index = 0;
    for ($i = 0; $i < PHP_INT_MAX; $i += $step) {
        $ids[$index] = $closure($index);
        $index++;
    }
    $end = hrtime(true);
    $output = str_pad(end($class) . " {$note}", 35) . number_format($total / (($end - $start) / 1e9)) . " ops/s\n";
    echo $output;
    file_put_contents(__DIR__ . '/RefPerf/perf.txt', $output, FILE_APPEND);

    return $ids;
}

$rawIds = bench(fn () => $generator->id(), $generator::class);

// encoders
$encodedIds = bench(fn (int $index) => $rotatedAlphabetEncoder->encode((int) $rawIds[$index]), $rotatedAlphabetEncoder::class, 'encode');
bench(fn (int $index) => $rotatedAlphabetEncoder->decode((string) $encodedIds[$index]), $rotatedAlphabetEncoder::class, 'decode');

$encodedIds = bench(fn (int $index) => $fixedLengthEncoder->encode((int) $rawIds[$index]), $fixedLengthEncoder::class, 'encode');
bench(fn (int $index) => $fixedLengthEncoder->decode((string) $encodedIds[$index]), $fixedLengthEncoder::class, 'decode');

$mask = ((1 << 16) - 1);
// serializers
$serializedIds = bench(fn (int $index) => $nativeSerializer->serialize([
    ($index >> 48) & $mask, ($index >> 32) & $mask, ($index >> 16) & $mask, $index & $mask,
]), $nativeSerializer::class, 'serialize');
bench(fn (int $index) => $nativeSerializer->deserialize((string) $serializedIds[$index]), $nativeSerializer::class, 'deserialize');

if (extension_loaded('bcmath')) {
    $serializedIds = bench(fn (int $index) => $BCMathSerializer->serialize([
        ($index >> 48) & $mask, ($index >> 32) & $mask, ($index >> 16) & $mask, $index & $mask,
    ]), $BCMathSerializer::class, 'serialize');
    bench(fn (int $index) => $BCMathSerializer->deserialize((string) $serializedIds[$index]), $BCMathSerializer::class, 'deserialize');
}

if (extension_loaded('gmp')) {
    $serializedIds = bench(fn (int $index) => $GMPSerializer->serialize([
        ($index >> 48) & $mask, ($index >> 32) & $mask, ($index >> 16) & $mask, $index & $mask,
    ]), $GMPSerializer::class, 'serialize');
    bench(fn (int $index) => $GMPSerializer->deserialize((string) $serializedIds[$index]), $GMPSerializer::class, 'deserialize');
}

$serializedIds = bench(fn (int $index) => $fixedLengthSerializer->serialize([
    ($index >> 48) & $mask, ($index >> 32) & $mask, ($index >> 16) & $mask, $index & $mask,
]), $fixedLengthSerializer::class, 'serialize');
bench(fn (int $index) => $fixedLengthSerializer->deserialize((string) $serializedIds[$index]), $fixedLengthSerializer::class, 'deserialize');

// encrypters
$encryptedIds = bench(fn (int $index) => $encrypter->encrypt((int) $rawIds[$index]), $encrypter::class, 'encrypt');
bench(fn (int $index) => $encrypter->decrypt((string) $encryptedIds[$index]), $encrypter::class, 'decrypt');

// signers
$signedIds = bench(fn (int $index) => $signer->getSignedId((string) $rawIds[$index]), $signer::class, 'sign');
bench(fn (int $index) => $signer->getIdFromSigned((string) $signedIds[$index]), $signer::class, 'verify');
