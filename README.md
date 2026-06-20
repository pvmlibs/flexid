[![CI Status](https://github.com/pvmlibs/flexid/actions/workflows/tests.yml/badge.svg)](https://github.com/pvmlibs/flexid/actions)
[![codecov](https://codecov.io/gh/pvmlibs/flexid/branch/master/graph/badge.svg?token=26GRS6RJRN)](https://codecov.io/gh/pvmlibs/flexid/branch/master)

# Distributed ID generator for PHP with ID handling tools

Features:
- Generate unique ID on one or multiple nodes/processes. Workers management is included for efficient ID pool usage
- Encode integer ID for shorter strings/obfuscate it with custom alphabet e.g. xzRYLSKxJH
- Encrypt integer ID when need to publish it and want to safely hide the real value
- Sign ID for integrity and authenticity, preventing tampering, enumeration and misuse (so user id cannot be used as invoice id)
- generated ID lifespan ranges from 292 to 292271 years
- superfast, see [Benchmarks](docs/Benchmarks.md)
- example integration with [Laravel](docs/LaravelIntegration.md)

Included tools (serializers, encrypters, signers) can also work with external ID, including ordinary autoincrement ID.
They try to solve common problem with exposing internal ID from database to public. Usually applications use something
like uuidv4/uuidv7/other random id which have impact on DB performance, memory usage and could also reveal information
on id creation date (uuidv7).
Now we can have performant integer ID in database and transform it to/from application on the fly, without storing
transformed id.

## Requirements

Core functionality doesn't require any extensions and dependencies, so you can generate ids, serialize them, encrypt and
sign on vanilla php, required are only:

1. 64-bit system
2. PHP >= 8.1

Some of additional features like some of encryptors, faster hashes, specialized worker resolvers requires additional
extensions.

## Installation

```shell
composer require pvmlibs/flexid
```

## Tools

1. Generator - generates ID using resolver. Manages requesting workers and creates sequence with group part.
   Generator should be used as singleton in application for performance and to assure proper time monotonicity. You can
   also define a fallback to other generator if it can't resolve worker id.
2. Worker resolvers - manage and provide worker id and ID configuration. For more read [Resolvers](docs/Resolvers.md)
    - RedisTimestepWorkerResolver, allows ID uniqueness, needs Redis/Valkey, most universal
    - RedisReservedWorkerResolver, allows ID uniqueness, needs Redis/Valkey, best for long processes
    - StaticWorkerResolver, allows ID uniqueness when provided explicit unique worker ID
    - RandomWorkerResolver, allows ID uniqueness, but only within one process
    - ApcuTimestepWorkerResolver, allows ID uniqueness only within group of processes sharing the same APCu
3. Encryptors - encrypts/decrypts ID. Encrypted ID have to use serializer for printable output.
   - Sparx64Encrypter - uses Sparx lightweight ARX-based 64-bit block cipher that can transform ID to other 64-bit number
                        with 128-bit key. No php extensions required. For the same input, will give the same (permutated) output.
   - AesEncrypter - uses AES with 128, 192 or 256-bit key. Block size is 128-bit so first 64 bits takes id, the second
                    one is used for HMAC sign. For the same input, will give the same (permutated) output.
                    Requires openssl extension. 
   - XChaCha20Encrypter - stream cipher with 256-bit key, 128-bit sign and 192-bit nonce so the same input generates
                          different output. Produces the longest output from encrypters (64 with base64 - 96 with hex).
                          Requires sodium extension.
4. Serializers - transforms 64 bits integer to string. By default, they use safe alphabet which prevent building random
                 words (except Base64Serializer). 
     - BaseSerializer   - supports only power of 2 alphabet lengths but don't require any php extensions, uses custom
                          alphabet and supports whole range of php 64-bit integer.
     - CustomSerializer - supports custom alphabet lengths and can produce slightly smaller output than BaseSerializer.
                          By default, supports only positive integers, negatives require BCMath or GMP
                          extension.
     - HashSerializer   - only for positive id, useful for e.g. encoding sequential id - they will look random
                          while still being short.
     - HexSerializer - uses only hexadecimal chars, but it's fast. Fixed length (16).
     - Base64Serializer - uses a-zA-z0-9-_ chars, fast but can create random words. Fixed length (11).
5. Signers - sign ID using HMAC with secret key. It can ensure that ID comes from us and was not changed (confidence
      depends mainly on sign length). Uses serializer for producing printable output. 
     - Signer - customizable signer, by default uses 64 bit of hash data (customizable up to 256 bit), length can be
            also reduced up to 1 char, depending on application needs (e.g. for simple id checksum). Out of the box does
            not require any php extensions, but some of the hash options may require sodium (siphash-2-4 or blake2b
            are recommended). Keep in mind that 64 bits are tradeoff between id integrity confidence and output length,
            generally application should always validate incoming id anyway. If you need to relay on authenticity then
            64 bits are probably to little, and you should consider 128-256 bit signs.


## Usage

IMPORTANT!
ID handling is part of application design, you need to evaluate what the application requirements are, like:
- required id generation throughput - validate generator and worker resolvers configuration
- how confidential public id needs to be? For stronger confidentiality, use encrypter, for not so critical id or when 
  you want as short output as possible, while still hiding real id, you can use HashSerializer.
- autoincrement id/timestamp based id choose. Timestamp based won't produce as short output as autoincrement id but can
  be globally unique.
- id signing does not replace proper authentication for resource access, it can act as additional layer

Parameters that should be constant through application lifetime to prevent ID overlapping and decoding issues:
- timestamp offset
- timestamp bitshift
- serializer parameters (if used) including used alphabet
- encrypter parameters (if used) including used alphabet and secret key
- signer settings

Other parameters like worker bits, sequence bits, group bits are pretty safe to manipulate in future - collision can 
happen only within the same timestep (max 1,07s) when concurrently using different parameters. If you want to
change/have generators with different worker bits and sequence bits working concurrently then set common group bits
and assign each generator type its own group id.

You can look at [ID overview](docs/IdOverview.md) to get some idea what to expect.

Some general guidance:

1. Use generator as singleton in application for performance and uniqueness guarantee (in StaticWorkerResolver and
   RandomWorkerResolver), unless you want to have generators with different configuration. Serializers, encrypters and
   signers should also be singletons.
2. When sending to JavaScript big id needs to be cast to string (JS does not handle 64-bit int), use serializer output.

Generate ID:
```php
// just generate some unique ID
$generator = new \Pvmlibs\FlexId\FlexIdGenerator(
    workerResolver: new \Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver(client: $redisClient)
);

$generator->id(); // 43526598068356096

// static worker id, uses process PID as worker id as example
$generator = new \Pvmlibs\FlexId\(
                workerResolver: new \Pvmlibs\FlexId\Resolvers\StaticWorkerResolver(
                    workerHandlerFn: fn () => getmypid(), workersBits: 8, sequenceBits: 8
                )
            );
$generator->id(); // 43524358613175296
```

Generate many ID more efficiently:
```php
$ids = $generator->bulkIds(1000); // array
```

Generate ID with encoding. Serializer can be also used to encode/decode any integer number (PHP_INT_MIN - PHP_INT_MAX):

```php
$generator = new \Pvmlibs\FlexId\FlexIdGenerator(
    workerResolver: new \Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver(client: $redisClient)
);

$serializer = new \Pvmlibs\FlexId\Serializers\HashSerializer();

// using helper container
$encodedId = new \Pvmlibs\FlexId\IdHandlers\EncodedId(
   serializer: $serializer,
   // signer: use SignerContract if id should be also signed
   generator: $generator, // optional, if you want to generate id
);

$id = $encodedId->generateId(); // 43581127276918784
$publicId = $encodedId->toPublicId($id); // LNvqjBKLnJ
$encodedId->fromPublicId($publicId); // 43581127276918784

// or use serializer directly
$id = $generator->id(); // 43581127276918784
$publicId = $serializer->serialize($id); // LNvqjBKLnJ
$serializer->serialize($publicId); // 43581127276918784
```

Generate ID with encrypting. Encryptor can be also used to encrypt/decrypt any integer number (PHP_INT_MIN - PHP_INT_MAX):

```php
$generator = new \Pvmlibs\FlexId\FlexIdGenerator(
    workerResolver: new \Pvmlibs\FlexId\Resolvers\RedisTimestepWorkerResolver(client: $redisClient)
);
$secret = \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter::generateSecret(); // use your own secret
$encrypter = new \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(
    secret: $secret,
    serializer: new \Pvmlibs\FlexId\Serializers\BaseSerializer()
);

// using helper container
$encryptedId = new \Pvmlibs\FlexId\IdHandlers\EncryptedId(
    encrypter: $encrypter,
    // signer: use SignerContract if id should be also signed
    generator: $generator, // optional, if you want to generate id
);

$id = $encryptedId->generateId(); // 43581127276918784
$publicId = $encryptedId->toPublicId($id); // yVyKqbkQDgYgR
$encryptedId->fromPublicId($publicId); // 43581127276918784

// or use encryptor directly
$id = $generator->id(); // 43581127276918784
$publicId = $encrypter->encrypt($id,''); // yVyKqbkQDgYgR
$encrypter->decrypt($publicId); // 43581127276918784
```

Sign ID directly. Signer can be also used to encrypt/decrypt any ID (as string):

```php
// use signer as id crc, 1 char sign
$signer = new \Pvmlibs\FlexId\Signers\Signer(
    secret: \Pvmlibs\FlexId\Signers\Signer::generateSecret(), // use your own key
    serializer: new \Pvmlibs\FlexId\Serializers\CustomSerializer(),
    hashAlgo: 'siphash-2-4', // siphash is few times faster than sha256
    maxSignLength: 1,
    onlyPositiveRange: true // optionally for short signs, much faster when using with CustomSerializer
);

$id = 'r8BnZxS';
$signed = $signer->getSignedId($id); // r8BnZxSQ
// get id from signed with validation
$id = $signer->getIdFromSigned($signed); // r8BnZxS

// for more integrity assurance use longer sign length (signBits). For shorter output and less assurance adjust maxSignLength.
$signer = new \Pvmlibs\FlexId\Signers\Signer(
    secret: \Pvmlibs\FlexId\Signers\Signer::generateSecret(), // use your own key
    serializer: new \Pvmlibs\FlexId\Serializers\BaseSerializer(),
    hashAlgo: 'siphash-2-4',
    signBits: 64,
);

$id = 'r8BnZxS';
$signed = $signer->getSignedId($id); // r8BnZxSDwJgwJWVxfJdx

// other examples of signed id
// encrypted id + default 64-bit sign
// r8BnZxSqxFfPZYcLLYzQ
// encrypted id + 128-bit sign
// r8BnZxSqxFfPZYcLLYzQJZMcRWcLqmvLD
```

Building ID Value Object (see also [Laravel Integration](docs/LaravelIntegration.md)):
```php
// base class for reuse
abstract class IdBaseHandler {
    use \Pvmlibs\FlexId\Concerns\HasIdHandler;

    // implement method returning IdHandlerContract[]
    protected static function getIdHandler(): array
    {
        static $class = null;
        $class ??= [
            // adjust for your needs, add another classes for e.g. key rotation
            new \Pvmlibs\FlexId\IdHandlers\EncodedId(serializer: new \Pvmlibs\FlexId\Serializers\HashSerializer()),
        ];
        return $class;
    }
    
    // called when cannot decode public id
    protected static function handleFromPublicIdException(\Exception $exception): void
    {
        throw $exception; // or e.g. throw 403
    }
}

class IdVO extends IdBaseHandler {
    protected static function idUniqueTypeName(): string
    {
        return 'foo';
    }
}


$idVO = new IdVO(internalId: 10);
echo $idVO->getPublicId(); // 1N1x
echo IdVO::fromPublicId('1N1x')->getInternalId(); // 10 
```

Backfill ID using Unix timestamp in microseconds. Make sure max sequence is enough for given timestep, sort timestamps
ascending or descending to prevent duplicates:
```php
$generator->idInTime(1779275145863184)) // 43585545820962816
```

Check performance, ID distribution in time, throughput with different timestamp bitshift and generator info:
```bash
php vendor/pvmlibs/flexid/src/Scripts/bench.php [--dist --info]
# this is more detailed
php vendor/pvmlibs/flexid/src/Scripts/perf.php
```

You can also use class IdStats with your configured generator to get results for this configuration.
