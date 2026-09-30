<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

use App\Shared\Domain\Bus\Query\Query;

/**
 * Raw filters as they come from the UI (query string): blank means "any".
 *
 * @implements Query<JobApplicationPage>
 */
final readonly class SearchJobApplicationsQuery implements Query
{
    public function __construct(
        public ?string $status = null,
        public ?string $jobOfferId = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = JobApplicationSearchCriteria::DEFAULT_PER_PAGE,
    ) {
    }
}
