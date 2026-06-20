<?php

declare(strict_types=1);

namespace Tests\Concerns;

use PHPUnit\Framework\TestCase;
use Pvmlibs\FlexId\Concerns\HasDistributedOffset;

/**
 * @internal
 */
final class HasIdHandlerTest extends TestCase
{
    public function testEncodedIdWithSigner(): void
    {
        $baseClassFoo = fn (int $id): DummyBaseEncodedId => new class($id) extends DummyBaseEncodedId {
            protected static function idUniqueTypeName(): string
            {
                return 'foo';
            }
        };
        $baseClassBar = fn (int $id): DummyBaseEncodedId => new class($id) extends DummyBaseEncodedId {
            protected static function idUniqueTypeName(): string
            {
                return 'bar';
            }
        };
        $this->validateHandlerClasses($baseClassFoo, $baseClassBar);
    }

    public function testEncryptedIdWithSigner(): void
    {
        $baseClassFoo = fn (int $id): DummyBaseEncryptedId => new class($id) extends DummyBaseEncryptedId {
            use HasDistributedOffset;

            protected static function idUniqueTypeName(): string
            {
                return 'foo';
            }
        };
        $baseClassBar = fn (int $id): DummyBaseEncryptedId => new class($id) extends DummyBaseEncryptedId {
            use HasDistributedOffset;

            protected static function idUniqueTypeName(): string
            {
                return 'bar';
            }
        };
        $this->validateHandlerClasses($baseClassFoo, $baseClassBar);
    }

    /**
     * @param \Closure(int $id): (DummyBaseEncodedId|DummyBaseEncryptedId) $fooBase
     * @param \Closure(int $id): (DummyBaseEncodedId|DummyBaseEncryptedId) $barBase
     */
    private function validateHandlerClasses(\Closure $fooBase, \Closure $barBase): void
    {
        try {
            $fooBase(-1);
            $this::fail('Negative id are not allowed');
        } catch (\RuntimeException) {
        }
        $id = $fooBase(1);
        $this::assertSame((string) $id->getInternalId(), (string) $id);
        $this::assertSame($id->getPublicId(), $id->jsonSerialize());

        try {
            $id::fromPublicId('');
            $this::fail('Should not decode empty string');
        } catch (DummyException) {
        }

        $publicIds = [];
        for ($i = 0; $i < 10; $i++) {
            $id = $i;
            $idFoo = $fooBase($id);
            $idBar = $barBase($id);
            $this::assertSame($id, $idFoo->getInternalId());
            $this::assertSame($id, $idBar->getInternalId());
            $publicFooId = $idFoo->getPublicId();
            $publicBarId = $idBar->getPublicId();
            $publicIds[] = $publicFooId;
            $publicIds[] = $publicBarId;
            $this::assertNotSame($publicFooId, $publicBarId);
            $this::assertSame($id, $idFoo::fromPublicId($publicFooId)->getInternalId());
            $this::assertSame($id, $idBar::fromPublicId($publicBarId)->getInternalId());
            $this::assertNotTrue($idFoo->equals($idBar));
            $this::assertTrue($idFoo->equals($idFoo));

            try {
                $idBar::fromPublicId($publicFooId);
                $this::fail('Should not decode other id type');
            } catch (DummyException) {
            }
        }
        // id offset is the same here but public id will be different due to sign part, which depends on idUniqueTypeName
        $this::assertCount(20, \array_unique($publicIds));
    }

}
