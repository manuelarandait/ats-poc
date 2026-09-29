<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

/**
 * Outcome of the AI enrichment as Recruitment stores it: a CV summary and a
 * relevance score for the position applied to.
 */
final readonly class AiScreening
{
    public const int SUMMARY_MAX_LENGTH = 2_000;

    private function __construct(
        public string $summary,
        public AiScore $score,
    ) {
    }

    public static function create(string $summary, AiScore $score): self
    {
        $summary = trim($summary);

        if ('' === $summary || mb_strlen($summary) > self::SUMMARY_MAX_LENGTH) {
            throw InvalidAiScreening::summaryLength(self::SUMMARY_MAX_LENGTH);
        }

        return new self($summary, $score);
    }
}
