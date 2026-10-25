## Laravel integration

Following integration assumes creating Value Object classes per id type, casts id in models, model id route binding and
id signing for integrity and tamper prevention - this will also prevent from using mixed id types in route dedicated for
specific id. This certainty depends on sign length though, you should use authentication against id anyway in application,
signing acts like additional layer, so much less wrong ids will hit database.
ID Value Objects also assure assigning correct id type in models and are helpful in static analyze tools.

1. Prepare id.php config file
```php
return [
    'secret_key' => env('ID_SECRET_KEY'), // generate KeyGen::generate(), can be used for encryption and signing
    'alphabet' => env('ID_ALPHABET'), // shuffled alphabet, especially important when encoding with HashSerializer
]
```

2. Create base class for id handling. It should be one per id handling method, e.g. encryption, encoding or e.g. different handling
for autoincrement id and timestamp based id.

```php
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Pvmlibs\FlexId\Concerns\HasIdHandler;
use Pvmlibs\FlexId\IdHandlers\EncryptedId;
use Pvmlibs\FlexId\Serializers\BaseSerializer;
use Pvmlibs\FlexId\Signers\Signer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class EncryptedIdAbstract implements Castable, \JsonSerializable
{
    use HasIdHandler;

    protected static function getIdHandler(): array
    {
        static $class = null; // resolve only once
        $class ??= [
            // You can add more classes for e.g. key rotation scenario, it will try from the first one
            // example for encoding and short sign for concise output (here 8 chars)
//            new EncodedId(
//                new HashSerializer(alphabet: config('id.alphabet'), minLength: 4),
//                new Signer(
//                    serializer: new CustomSerializer(),
//                    hmac: new Pvmlibs\FlexId\HMAC\Sha256HMAC(config('id.secret_key')), // use SiphashHMAC for better performance
//                    maxSignLength: 4,
//                    onlyPositiveRange: true,
//                ),
//                null, // here you can optionally pass IdGeneratorContract if you want to generate id from app
//            ),            
            // example for encrypted (64-bit block, 128-bit key) and signed id (64-bit) for better confidentiality (but longer output, here ~26 chars)
            new EncryptedId(
                new \Pvmlibs\FlexId\Encrypters\Sparx64Encrypter(config('id.secret_key'), new BaseSerializer()),
                new Signer( // optional but recommended, we can use the same key, sign will use hkdf key derivation
                    serializer: new BaseSerializer(),
                    hmac: new Pvmlibs\FlexId\HMAC\Sha256HMAC(config('id.secret_key')) // use SiphashHMAC for better performance
                ),
                null, // here you can optionally pass IdGeneratorContract if you want to generate id from app
            ),
            // example for encryption (128-bit block, 256-bit key) with build-in sign (64 bit) (~26 chars)
            // use Sparx128TurboEncrypter if possible
            new EncryptedId(
                new \Pvmlibs\FlexId\Encrypters\Sparx128Encrypter(config('id.secret_key'), new BaseSerializer()),
                null, // id have already a sign, but you can add additional sign
                null, // here you can optionally pass IdGeneratorContract if you want to generate id from app
            ),            
        ];
        return $class;
    }

    protected static function handleFromPublicIdException(\Exception $exception): never
    {
        // here define action when cannot decode public id, probably access denied when in http context, e.g.:
        report($exception);
        throw new AccessDeniedHttpException();
    }

    // cast to string but integer, used for eloquent
    public function __toString(): string
    {
        return (string) $this->internalId;
    }
    
    /**
     * For eloquent model cast
     * @param array<string> $arguments
     * @return CastsAttributes
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): mixed
            {
                if ($value === null) {
                    return null;
                }

                return new ($model->getCasts()[$key])($value);
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?int
            {
                if (\is_int($value)) {
                    return $value;
                }

                return $value?->getInternalId();
            }
        };
    }
}
```

3. Create classes for each id type extending base class, best for each primary key in models:
```php
class IdTypeFoo extends BaseIncrementIdType1
{
    // use HasDistributedOffset trait when encrypting autoincrement id, it will distribute it on negative int range, 
    // so encrypted output will be different for the same integer id with different id types (unique idUniqueTypeName).
    // This is not suitable for short encoded id, in that case you can apply e.g. 1 million offset levels with method idOffset()
    // Custom offset is also not recommended for timestamp based id, as they are globally unique anyway (if correctly configured)
    
    // use HasDistributedOffset;

    // this method should return unique id type. It's literal string and not class name so it doesn't change with refactor
    // this is used by signing id so id can be validated against its type. When changed, previous encoded id won't be decoded
    // with the same IdHandlerContract configuration
    protected static function idUniqueTypeName(): string
    {
        return 'foo';
    }
}
```

4. Modify models to use id classes:
```php
class ModelFoo extends Model
{
    // so it will cast id class to string
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'id' => IdTypeFoo::class,
            // foreign ids also should be cast to their classes
        ];
    }

    // this will automatically handle public id in routes for this model 
    public function resolveRouteBinding($value, $field = null): self|null
    {
        return $this->find(IdTypeFoo::fromPublicId($value));
    }
}
```

5. Test it:
```php
// when requesting model with public id in route, it should return it:
// url /getmodel/KmgLPKBFFBVVmxjzmPvDGVDCC
Route::get('/getmodel/{foo}', function (ModelFoo $foo) {
    // $foo should be loaded, id class in response will return public id
    return response($foo->id); // KmgLPKBFFBVVmxjzmPvDGVDCC
});
```

6. Optionally, you can use package barryvdh/laravel-ide-helper, which can generate mixins with properties types for models.
For custom object casts you need to add own hook:
```php
class CustomCastsModelHook implements ModelHookInterface
   {
   public function run(ModelsCommand $command, Model $model): void
   {
       $casts = $model->getCasts();

        $table = $model->getTable();
        $schema = $model->getConnection()->getSchemaBuilder();
        $columns = $schema->getColumns($table);

        foreach ($casts as $key => $cast) {
            $tableColumn = array_find($columns, static fn (array $column): bool => $column['name'] === $key);

            if ($tableColumn !== null) {
                if (is_string($cast) && str_contains($cast, '\\')) {
                    $command->setProperty($key, '\\'.$cast, true, true, nullable: $tableColumn['nullable']);
                }
            }
        }
   }
}
```
And add it to ide-helper.php:
```php
    'model_hooks' => [
        CustomCastsModelHook::class,
    ],
```