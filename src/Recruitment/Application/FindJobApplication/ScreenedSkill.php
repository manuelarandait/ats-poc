<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobApplication;

/**
 * One skill the position asks for, and whether the AI found it in the CV.
 */
final readonly class ScreenedSkill
{
    public function __construct(
        public string $skill,
        public bool $required,
        public bool $matched,
    ) {
    }
}
