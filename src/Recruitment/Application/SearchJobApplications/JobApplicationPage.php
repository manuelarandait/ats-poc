<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

final readonly class JobApplicationPage
{
    /**
     * @param list<JobApplicationSummary> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->pages();
    }
}
