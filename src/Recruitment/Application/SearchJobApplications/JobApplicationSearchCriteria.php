<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Shared\Domain\Pagination\PageRequest;

/**
 * Validated, normalised filters: raw query-string values become typed or null.
 * An unknown sort or direction falls back to the default (newest first), like
 * an unsupported page size: it's presentation, not a wrong filter.
 */
final readonly class JobApplicationSearchCriteria
{
    private function __construct(
        public ?JobApplicationStatus $status,
        public ?JobOfferId $jobOfferId,
        public ?string $search,
        public PageRequest $pageRequest,
        public JobApplicationSort $sort,
        public SortDirection $direction,
    ) {
    }

    public static function fromQuery(SearchJobApplicationsQuery $query): self
    {
        $search = trim($query->search ?? '');
        $sort = JobApplicationSort::tryFrom(trim($query->sort ?? '')) ?? JobApplicationSort::default();

        return new self(
            self::isBlank($query->status) ? null : JobApplicationStatus::fromValue((string) $query->status),
            self::isBlank($query->jobOfferId) ? null : JobOfferId::fromString((string) $query->jobOfferId),
            '' === $search ? null : $search,
            PageRequest::of($query->page, $query->perPage),
            $sort,
            SortDirection::tryFrom(trim($query->direction ?? '')) ?? $sort->defaultDirection(),
        );
    }

    public function onPage(int $page): self
    {
        return new self($this->status, $this->jobOfferId, $this->search, $this->pageRequest->onPage($page), $this->sort, $this->direction);
    }

    private static function isBlank(?string $value): bool
    {
        return null === $value || '' === trim($value);
    }
}
