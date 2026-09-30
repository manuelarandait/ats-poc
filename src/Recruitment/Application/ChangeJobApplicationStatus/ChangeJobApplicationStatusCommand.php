<?php

declare(strict_types=1);

namespace App\Recruitment\Application\ChangeJobApplicationStatus;

use App\Shared\Domain\Bus\Command\Command;

final readonly class ChangeJobApplicationStatusCommand implements Command
{
    public function __construct(
        public string $id,
        public string $status,
    ) {
    }
}
