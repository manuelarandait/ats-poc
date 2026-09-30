<?php

declare(strict_types=1);

namespace App\Screening\Domain;

/**
 * What the AI produced for a CV: a concise summary and a 0–100 relevance score
 * for the position.
 */
final readonly class CvAnalysis
{
    public const int MIN_SCORE = 0;
    public const int MAX_SCORE = 100;

    private function __construct(
        public string $summary,
        public int $score,
    ) {
    }

    public static function create(string $summary, int $score): self
    {
        $summary = trim($summary);

        if ('' === $summary) {
            throw InvalidCvAnalysis::emptySummary();
        }

        if ($score < self::MIN_SCORE || $score > self::MAX_SCORE) {
            throw InvalidCvAnalysis::scoreOutOfRange($score);
        }

        return new self($summary, $score);
    }
}
