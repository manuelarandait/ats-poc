<?php

declare(strict_types=1);

namespace App\Screening\Domain;

/**
 * What the AI produced for a CV: a concise summary of the candidate, a 0–100
 * relevance score for the position and, skill by skill, what the CV covers of
 * what the position asks for (structured output, as asked from a real LLM).
 */
final readonly class CvAnalysis
{
    public const int MIN_SCORE = 0;
    public const int MAX_SCORE = 100;

    /**
     * @param list<SkillMatch> $skills
     */
    private function __construct(
        public string $summary,
        public int $score,
        public array $skills,
    ) {
    }

    /**
     * @param list<SkillMatch> $skills
     */
    public static function create(string $summary, int $score, array $skills = []): self
    {
        $summary = trim($summary);

        if ('' === $summary) {
            throw InvalidCvAnalysis::emptySummary();
        }

        if ($score < self::MIN_SCORE || $score > self::MAX_SCORE) {
            throw InvalidCvAnalysis::scoreOutOfRange($score);
        }

        return new self($summary, $score, $skills);
    }
}
