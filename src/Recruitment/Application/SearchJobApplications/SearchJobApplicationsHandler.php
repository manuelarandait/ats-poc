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
        return $this->readModel->search(JobApplicationSearchCriteria::fromQuery($query));
    }
}
