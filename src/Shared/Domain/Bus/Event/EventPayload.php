<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Event;

/**
 * Typed access to the data of an incoming event, which comes from outside the
 * process and therefore can't be trusted to have the expected shape.
 */
final readonly class EventPayload
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private array $data)
    {
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : throw InvalidEventPayload::field($key, 'string');
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        return \is_int($value) ? $value : throw InvalidEventPayload::field($key, 'int');
    }

    /**
     * A default lets a consumer accept events published before the field
     * existed (additive, backward-compatible contract change).
     *
     * @param array<array-key, mixed>|null $default
     *
     * @return array<array-key, mixed>
     */
    public function array(string $key, ?array $default = null): array
    {
        $value = $this->data[$key] ?? $default;

        return \is_array($value) ? $value : throw InvalidEventPayload::field($key, 'array');
    }
}
