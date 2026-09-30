<?php

declare(strict_types=1);

namespace App\Screening\Domain;

/**
 * Transient failure of the AI provider (timeout, rate limit…). Not a business
 * rule violation: the same request may succeed if retried.
 */
final class CvAnalysisUnavailable extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('CV analysis unavailable: %s', $reason));
    }
}
