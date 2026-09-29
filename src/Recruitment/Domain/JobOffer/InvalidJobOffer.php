<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobOffer;

use App\Shared\Domain\DomainError;

final class InvalidJobOffer extends DomainError
{
    public static function title(string $title, int $maxLength): self
    {
        return new self(\sprintf('Job offer title must have between 1 and %d characters, "%s" given.', $maxLength, $title));
    }

    public static function emptyDescription(): self
    {
        return new self('Job offer description cannot be empty.');
    }
}
