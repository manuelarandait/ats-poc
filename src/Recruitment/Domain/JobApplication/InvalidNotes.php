<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

use App\Shared\Domain\DomainError;

final class InvalidNotes extends DomainError
{
    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('Notes cannot exceed %d characters.', $maxLength));
    }
}
