<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\GuidType;

/**
 * Maps an id value object to a native UUID column.
 *
 * @template T of Uuid
 */
abstract class UuidType extends GuidType
{
    /**
     * @return class-string<T>
     */
    abstract protected function uuidClass(): string;

    /**
     * @return T|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        $class = $this->uuidClass();

        if (null === $value || $value instanceof $class) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, static::class, ['null', 'string']);
        }

        return $class::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof Uuid => $value->value,
            \is_string($value) => $value,
            default => throw InvalidType::new($value, static::class, ['null', 'string', Uuid::class]),
        };
    }
}
