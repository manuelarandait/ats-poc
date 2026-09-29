<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

use App\Shared\Domain\DomainError;

final class UnknownJobApplicationStatus extends DomainError
{
    public static function fromValue(string $value): self
    {
        return new self(\sprintf('"%s" is not a job application status.', $value));
    }
}
