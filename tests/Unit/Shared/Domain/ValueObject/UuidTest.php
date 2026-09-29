<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\InvalidUuid;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function test_it_normalizes_a_valid_uuid_to_lowercase(): void
    {
        $id = SomeId::fromString(' 0192F5A0-7C3B-7D2E-9A1B-3C4D5E6F7A8B ');

        self::assertSame('0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b', $id->value);
    }

    #[DataProvider('invalidUuids')]
    public function test_it_rejects_an_invalid_uuid(string $value): void
    {
        $this->expectException(InvalidUuid::class);

        SomeId::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUuids(): iterable
    {
        yield 'empty' => [''];
        yield 'random text' => ['not-a-uuid'];
        yield 'missing a group' => ['0192f5a0-7c3b-7d2e-3c4d5e6f7a8b'];
        yield 'non hex characters' => ['0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7z8b'];
    }

    public function test_ids_with_same_value_and_type_are_equal(): void
    {
        $value = '0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b';

        self::assertTrue(SomeId::fromString($value)->equals(SomeId::fromString($value)));
    }

    public function test_ids_of_different_types_are_never_equal(): void
    {
        $value = '0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b';

        self::assertFalse(SomeId::fromString($value)->equals(OtherId::fromString($value)));
    }
}

final readonly class SomeId extends Uuid
{
}

final readonly class OtherId extends Uuid
{
}
