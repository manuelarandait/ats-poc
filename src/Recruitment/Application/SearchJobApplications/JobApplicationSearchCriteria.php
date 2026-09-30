<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobOffer\JobOfferId;

/**
 * Validated, normalised filters: raw query-string values become typed or null.
 */
final readonly class JobApplicationSearchCriteria
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 100;

    private function __construct(
        public ?JobApplicationStatus $status,
        public ?JobOfferId $jobOfferId,
        public ?string $search,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromQuery(SearchJobApplicationsQuery $query): self
    {
        $search = trim($query->search ?? '');

        return new self(
            self::isBlank($query->status) ? null : JobApplicationStatus::fromValue((string) $query->status),
            self::isBlank($query->jobOfferId) ? null : JobOfferId::fromString((string) $query->jobOfferId),
            '' === $search ? null : $search,
            max(1, $query->page),
            min(max(1, $query->perPage), self::MAX_PER_PAGE),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    private static function isBlank(?string $value): bool
    {
        return null === $value || '' === trim($value);
    }
}
