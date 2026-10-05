<?php

declare(strict_types=1);

namespace App\Screening\Domain;

/**
 * One skill the position asks for, and whether the CV shows it. Required
 * skills come from the description; nice-to-have ones follow a "Nice to have"
 * heading.
 */
final readonly class SkillMatch
{
    public function __construct(
        public string $skill,
        public bool $required,
        public bool $matched,
    ) {
    }

    /**
     * @return array{skill: string, required: bool, matched: bool}
     */
    public function toPrimitives(): array
    {
        return ['skill' => $this->skill, 'required' => $this->required, 'matched' => $this->matched];
    }
}
