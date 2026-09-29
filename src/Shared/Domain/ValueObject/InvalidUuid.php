<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\DomainError;

final class InvalidUuid extends DomainError
{
    public static function fromValue(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid UUID.', $value));
    }
}
