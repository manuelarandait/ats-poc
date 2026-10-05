<?php

declare(strict_types=1);

namespace App\Recruitment\Application\CompleteJobApplicationScreening;

use App\Shared\Domain\Bus\Command\Command;

final readonly class CompleteJobApplicationScreeningCommand implements Command
{
    /**
     * @param list<array{skill: string, required: bool, matched: bool}> $skills
     */
    public function __construct(
        public string $id,
        public string $summary,
        public int $score,
        public array $skills = [],
    ) {
    }
}
