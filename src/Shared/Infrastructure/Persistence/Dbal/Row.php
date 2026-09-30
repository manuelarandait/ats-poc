<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Dbal;

/**
 * Typed access to a raw database row (DBAL returns loosely typed values).
 */
final readonly class Row
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values)
    {
    }

    public function string(string $column): string
    {
        return $this->nullableString($column) ?? throw new \UnexpectedValueException(\sprintf('Column "%s" is null.', $column));
    }

    public function nullableString(string $column): ?string
    {
        $value = $this->values[$column] ?? null;

        return match (true) {
            null === $value, \is_string($value) => $value,
            \is_scalar($value) => (string) $value,
            default => throw new \UnexpectedValueException(\sprintf('Column "%s" is not a string.', $column)),
        };
    }

    public function int(string $column): int
    {
        return $this->nullableInt($column) ?? throw new \UnexpectedValueException(\sprintf('Column "%s" is null.', $column));
    }

    public function nullableInt(string $column): ?int
    {
        $value = $this->values[$column] ?? null;

        return match (true) {
            null === $value, \is_int($value) => $value,
            \is_string($value) && is_numeric($value) => (int) $value,
            default => throw new \UnexpectedValueException(\sprintf('Column "%s" is not an integer.', $column)),
        };
    }

    public function date(string $column): \DateTimeImmutable
    {
        return $this->nullableDate($column) ?? throw new \UnexpectedValueException(\sprintf('Column "%s" is null.', $column));
    }

    public function nullableDate(string $column): ?\DateTimeImmutable
    {
        $value = $this->nullableString($column);

        return null === $value ? null : new \DateTimeImmutable($value);
    }
}
