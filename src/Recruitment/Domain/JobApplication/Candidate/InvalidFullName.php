<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication\Candidate;

use App\Shared\Domain\DomainError;

final class InvalidFullName extends DomainError
{
    public static function length(string $value, int $min, int $max): self
    {
        return new self(\sprintf('Full name must have between %d and %d characters, "%s" given.', $min, $max, $value));
    }
}
