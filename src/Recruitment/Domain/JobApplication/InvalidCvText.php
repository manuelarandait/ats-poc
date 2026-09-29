<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

use App\Shared\Domain\DomainError;

final class InvalidCvText extends DomainError
{
    public static function empty(): self
    {
        return new self('CV text cannot be empty.');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('CV text cannot exceed %d characters.', $maxLength));
    }
}
