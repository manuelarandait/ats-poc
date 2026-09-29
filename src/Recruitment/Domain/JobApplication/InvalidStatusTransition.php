<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

use App\Shared\Domain\DomainError;

final class InvalidStatusTransition extends DomainError
{
    public static function between(JobApplicationStatus $from, JobApplicationStatus $to): self
    {
        return new self(\sprintf('A job application cannot move from "%s" to "%s".', $from->value, $to->value));
    }
}
