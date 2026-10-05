<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

use App\Shared\Domain\DomainError;

final class InvalidAiScreening extends DomainError
{
    public static function scoreOutOfRange(int $score, int $min, int $max): self
    {
        return new self(\sprintf('AI score must be between %d and %d, %d given.', $min, $max, $score));
    }

    public static function skillName(int $maxLength): self
    {
        return new self(\sprintf('A skill name must have between 1 and %d characters.', $maxLength));
    }

    public static function summaryLength(int $maxLength): self
    {
        return new self(\sprintf('AI summary must have between 1 and %d characters.', $maxLength));
    }
}
