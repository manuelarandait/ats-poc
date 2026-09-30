<?php

declare(strict_types=1);

namespace App\Screening\Domain;

use App\Shared\Domain\DomainError;

final class InvalidCvAnalysis extends DomainError
{
    public static function emptySummary(): self
    {
        return new self('A CV analysis needs a summary.');
    }

    public static function scoreOutOfRange(int $score): self
    {
        return new self(\sprintf('A CV analysis score must be between %d and %d, %d given.', CvAnalysis::MIN_SCORE, CvAnalysis::MAX_SCORE, $score));
    }
}
