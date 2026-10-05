<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

/**
 * One skill the position asks for, and whether the AI found it in the CV.
 */
final readonly class SkillMatch
{
    private const int MAX_LENGTH = 60;

    private function __construct(
        public string $skill,
        public bool $required,
        public bool $matched,
    ) {
    }

    public static function create(string $skill, bool $required, bool $matched): self
    {
        $skill = trim($skill);

        if ('' === $skill || mb_strlen($skill) > self::MAX_LENGTH) {
            throw InvalidAiScreening::skillName(self::MAX_LENGTH);
        }

        return new self($skill, $required, $matched);
    }
}
