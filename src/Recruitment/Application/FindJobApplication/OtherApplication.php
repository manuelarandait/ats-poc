<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobApplication;

/**
 * Another application sent from the same email address. Grouped on the read
 * side only: the email isn't verified, so it is a hint for the recruiter, not
 * a confirmed identity (see "Trade-offs and next steps" in the architecture doc).
 */
final readonly class OtherApplication
{
    public function __construct(
        public string $id,
        public string $positionTitle,
        public string $status,
        public string $screeningStatus,
        public ?int $aiScore,
        public \DateTimeImmutable $appliedAt,
    ) {
    }
}
