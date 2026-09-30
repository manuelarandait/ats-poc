<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\StringType;

/**
 * Maps a single-value, string-backed value object to one column. Rebuilding
 * goes through the value object's named constructor, so loaded data is
 * validated like any other input. Nullable columns simply hold NULL.
 *
 * @template T of object
 */
abstract class StringValueObjectType extends StringType
{
    /**
     * @return class-string<T>
     */
    abstract protected function valueObjectClass(): string;

    /**
     * @return T
     */
    abstract protected function fromString(string $value): object;

    /**
     * @param T $valueObject
     */
    abstract protected function toString(object $valueObject): string;

    /**
     * @return T|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?object
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, static::class, ['null', 'string']);
        }

        return $this->fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $class = $this->valueObjectClass();

        return match (true) {
            null === $value => null,
            $value instanceof $class => $this->toString($value),
            default => throw InvalidType::new($value, static::class, ['null', $class]),
        };
    }
}
