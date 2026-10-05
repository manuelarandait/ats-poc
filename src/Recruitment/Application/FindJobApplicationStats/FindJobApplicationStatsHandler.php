<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobApplicationStats;

use App\Recruitment\Application\JobApplicationReadModel;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Shared\Domain\Bus\Query\QueryHandler;

final readonly class FindJobApplicationStatsHandler implements QueryHandler
{
    public function __construct(private JobApplicationReadModel $readModel)
    {
    }

    public function __invoke(FindJobApplicationStatsQuery $query): JobApplicationStats
    {
        $jobOfferId = trim($query->jobOfferId ?? '');
        $search = trim($query->search ?? '');

        return $this->readModel->stats(
            '' === $jobOfferId ? null : JobOfferId::fromString($jobOfferId),
            '' === $search ? null : $search,
        );
    }
}
