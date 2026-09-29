<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SubmitJobApplication;

use App\Shared\Domain\Bus\Command\Command;

/**
 * The id is chosen by the caller (UUID v7) so it can redirect to the new
 * application without the command returning anything.
 */
final readonly class SubmitJobApplicationCommand implements Command
{
    public function __construct(
        public string $id,
        public string $jobOfferId,
        public string $fullName,
        public string $email,
        public ?string $phone,
        public string $cv,
        public ?string $notes,
    ) {
    }
}
