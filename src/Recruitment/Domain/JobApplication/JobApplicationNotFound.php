<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

use App\Shared\Domain\DomainError;

final class JobApplicationNotFound extends DomainError
{
    public static function withId(JobApplicationId $id): self
    {
        return new self(\sprintf('Job application "%s" does not exist.', $id->value));
    }
}
