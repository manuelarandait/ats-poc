<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

/**
 * One row of the applications list.
 */
final readonly class JobApplicationSummary
{
    public function __construct(
        public string $id,
        public string $candidateName,
        public string $candidateEmail,
        public string $jobOfferId,
        public string $positionTitle,
        public string $status,
        public string $screeningStatus,
        public ?int $aiScore,
        public \DateTimeImmutable $appliedAt,
        /** Applications sent from this email address, this one included. */
        public int $applicationsFromEmail = 1,
    ) {
    }
}
