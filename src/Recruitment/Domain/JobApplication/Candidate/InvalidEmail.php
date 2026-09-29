<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication\Candidate;

use App\Shared\Domain\DomainError;

final class InvalidEmail extends DomainError
{
    public static function fromValue(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid email address.', $value));
    }
}
