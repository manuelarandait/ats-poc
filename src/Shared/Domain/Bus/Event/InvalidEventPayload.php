<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Event;

final class InvalidEventPayload extends \InvalidArgumentException
{
    public static function field(string $key, string $expectedType): self
    {
        return new self(\sprintf('Event payload field "%s" is missing or is not a %s.', $key, $expectedType));
    }
}
