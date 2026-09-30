<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FailJobApplicationScreening;

use App\Shared\Domain\Bus\Command\Command;

final readonly class FailJobApplicationScreeningCommand implements Command
{
    public function __construct(
        public string $id,
        public string $reason,
    ) {
    }
}
