<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Identity value object. The domain only validates the format; generating new
 * ids (UUID v7) is an infrastructure concern, done before dispatching a command.
 */
abstract readonly class Uuid implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    final private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): static
    {
        $normalized = strtolower(trim($value));

        if (1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidUuid::fromValue($value);
        }

        return new static($normalized);
    }

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
