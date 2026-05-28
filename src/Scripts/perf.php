<?php

declare(strict_types=1);

use Pvmlibs\FlexId\Encoders\EncoderContract;
use Pvmlibs\FlexId\Encoders\FixedLengthEncoder;
use Pvmlibs\FlexId\Encoders\HexEncoder;
use Pvmlibs\FlexId\Encoders\RotatedAlphabetEncoder;
use Pvmlibs\FlexId\Encrypters\Sparx64Encrypter;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Serializers\BCMathSerializer;
use Pvmlibs\FlexId\Serializers\FixedLengthSerializer;
use Pvmlibs\FlexId\Serializers\GMPSerializer;
use Pvmlibs\FlexId\Serializers\HexSerializer;
use Pvmlibs\FlexId\Serializers\NativeSerializer;
use Pvmlibs\FlexId\Serializers\SerializerContract;

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
$hexSerializer = new HexSerializer();

$rotatedAlphabetEncoder = new RotatedAlphabetEncoder();
$fixedLengthEncoder = new FixedLengthEncoder();
$hexEncoder = new HexEncoder();

$signer = new Pvmlibs\FlexId\Signers\Signer(
    serializer: new NativeSerializer(),
    key: 'rCl29//aZ51LjLQZKUbMUA==',
    hashAlgo: 'tiger128,3', // tiger is always available
);

if (extension_loaded('sodium')) {
    $fastSigner = new Pvmlibs\FlexId\Signers\FastSigner(
        key: 'rCl29//aZ51LjLQZKUbMUA==',
    );
}

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

$encodedIds = bench(fn (int $index) => $hexEncoder->encode((int) $fullRangeRands[$index]), $hexEncoder::class, 'encode');
bench(fn (int $index) => $hexEncoder->decode((string) $encodedIds[$index]), $hexEncoder::class, 'decode');

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

$serializedIds = bench(fn (int $index) => $hexSerializer->serialize([
    $serializerRands[$index], $serializerRands[$index], $serializerRands[$index], $serializerRands[$index],
]), $hexSerializer::class, 'serialize');
bench(fn (int $index) => $hexSerializer->deserialize((string) $serializedIds[$index]), $hexSerializer::class, 'deserialize');

// encrypters
$encryptedIds = bench(fn (int $index) => $encrypter->encrypt((int) $fullRangeRands[$index]), $encrypter::class, 'encrypt');
bench(fn (int $index) => $encrypter->decrypt((string) $encryptedIds[$index]), $encrypter::class, 'decrypt');

// signers
$signedIds = bench(fn (int $index) => $signer->getSignedId((string) $serializerRands[$index]), $signer::class, 'sign');
bench(fn (int $index) => $signer->getIdFromSigned((string) $signedIds[$index]), $signer::class, 'verify');

if (extension_loaded('sodium')) {
    $signedIds = bench(fn (int $index) => $fastSigner->getSignedId((string) $serializerRands[$index]), $fastSigner::class, 'sign');
    bench(fn (int $index) => $fastSigner->getIdFromSigned((string) $signedIds[$index]), $fastSigner::class, 'verify');
}

$modes = [];
if ($argv[1] === '--ext' && (($mode = (int) $argv[2]) <= 3)) {
    for ($i = 1; $i <= 2; $i++) {
        if (($mode & $i) > 0) {
            $modes[] = $i;
        }
    }
}

if ($modes === []) {
    exit;
}

$encoders = [
    $hexEncoder,
    $fixedLengthEncoder,
    $rotatedAlphabetEncoder,
];

$serializers = [
    $hexSerializer,
    $fixedLengthSerializer,
    $nativeSerializer,
];

if (extension_loaded('bcmath')) {
    $serializers[] = $BCMathSerializer;
}

/**
 * @param list<EncoderContract|SerializerContract>                             $objects
 * @param Closure(EncoderContract|SerializerContract $object, int $id): string $closure
 */
function stats(string $name, array $objects, Closure $closure, int $mode): void
{
    global $fullRangeRands;
    $headerWidth = 32;
    $cellWidth = 35;
    $separatorHeader = array_fill(0, $headerWidth, '-');
    $separatorHeader = implode('', $separatorHeader) . '|';
    $separatorCell = array_fill(0, $cellWidth, '-');
    $separatorCell = implode('', $separatorCell) . '|';
    echo str_pad($name, $headerWidth) . '|';

    foreach ($objects as $object) {
        $class = explode('\\', $object::class);
        echo str_pad(end($class), $cellWidth, ' ', STR_PAD_LEFT) . '|';
    }
    echo "\n" . $separatorHeader;
    foreach ($objects as $object) {
        echo $separatorCell;
    }

    if (($mode & 1) > 0) {
        echo "\n";
        echo str_pad('Time [ms]', $headerWidth) . '|';

        foreach ($objects as $object) {
            $start = hrtime(true);
            for ($i = 0; $i < 100; $i++) {
                $closure($object, $fullRangeRands[$i]);
            }
            $end = hrtime(true);
            echo str_pad((string) (($end - $start) / 1e6), $cellWidth, ' ', STR_PAD_LEFT) . '|';
        }
    }
    if (($mode & 2) > 0) {
        echo "\n";
        echo str_pad('Example for ID 57439', $headerWidth) . '|';

        foreach ($objects as $object) {
            echo str_pad($closure($object, 57439), $cellWidth, ' ', STR_PAD_LEFT) . '|';
        }

        echo str_pad("\nExample for ID 44275863723996160", $headerWidth) . '|';

        foreach ($objects as $object) {
            echo str_pad($closure($object, 44275863723996160), $cellWidth, ' ', STR_PAD_LEFT) . '|';
        }
    }
    echo "\n";
}

echo "From fastest, time for 100 operations - simulate transform ID in pagination:\n";
echo "Security grading from the lowest: encoding, encoding + signing, encrypting, encrypting + signing\n";
echo "Encoding ID:\n\n";

$encrypters = [];
foreach ($serializers as $serializer) {
    $encrypters[$serializer::class] = new Sparx64Encrypter(
        secret: 'rCl29//aZ51LjLQZKUbMUA==',
        serializer: $serializer,
    );
}

foreach ($modes as $i) {
    // encoding
    stats('Encoder', $encoders, fn (EncoderContract|SerializerContract $object, int $id) => $object->encode($id), $i); // @phpstan-ignore method.notFound

    if (extension_loaded('sodium')) {
        echo "\n\nEncoding + signing ID (FastSigner, SipHash2-4) - fastest 64-bit sign:\n\n";
        stats('Encoder', $encoders, fn (EncoderContract|SerializerContract $object, int $id) => $fastSigner->getSignedId($object->encode($id)), $i); // @phpstan-ignore method.notFound
    }

    echo "\n\nEncoding + signing ID (Signer, SipHash2-4, BCMathSerializer) - shortest 64-bit sign:\n\n";
    stats('Encoder', $encoders, fn (EncoderContract|SerializerContract $object, int $id) => $signer->getSignedId($object->encode($id)), $i); // @phpstan-ignore method.notFound

    echo "\n\nEncrypting ID:\n\n";

    // encrypting
    stats('Serializer', $serializers, fn (EncoderContract|SerializerContract $object, int $id) => $encrypters[$object::class]->encrypt($id), $i);

    if (extension_loaded('sodium')) {
        echo "\n\nEncrypting + signing ID (FastSigner, SipHash2-4) - fastest 64-bit sign:\n\n";
        stats('Serializer', $serializers, fn (EncoderContract|SerializerContract $object, int $id) => $fastSigner->getSignedId($encrypters[$object::class]->encrypt($id)), $i);
    }

    echo "\n\nEncrypting + signing ID (Signer, SipHash2-4, BCMathSerializer) - shortest 64-bit sign:\n\n";
    stats('Serializer', $serializers, fn (EncoderContract|SerializerContract $object, int $id) => $signer->getSignedId($encrypters[$object::class]->encrypt($id)), $i);
    echo "\n\n";
}
