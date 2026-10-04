<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

use App\Recruitment\Application\JobApplicationReadModel;
use App\Shared\Domain\Bus\Query\QueryHandler;

final readonly class SearchJobApplicationsHandler implements QueryHandler
{
    public function __construct(private JobApplicationReadModel $readModel)
    {
    }

    public function __invoke(SearchJobApplicationsQuery $query): JobApplicationPage
    {
        $criteria = JobApplicationSearchCriteria::fromQuery($query);
        $page = $this->readModel->search($criteria);

        // A page past the end (e.g. after narrowing the filters) gives the last one instead of nothing.
        if ($page->pagination->isPastTheEnd()) {
            return $this->readModel->search($criteria->onPage($page->pagination->pages()));
        }

        return $page;
    }
}
