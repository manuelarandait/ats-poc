<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

/**
 * Relevance of the candidate for the position, 0 (none) to 100 (perfect fit).
 */
final readonly class AiScore
{
    public const int MIN = 0;
    public const int MAX = 100;

    private function __construct(public int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidAiScreening::scoreOutOfRange($value, self::MIN, self::MAX);
        }

        return new self($value);
    }
}
