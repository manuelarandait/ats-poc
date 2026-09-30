<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobApplication;

use App\Recruitment\Application\JobApplicationReadModel;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Shared\Domain\Bus\Query\QueryHandler;

final readonly class FindJobApplicationHandler implements QueryHandler
{
    public function __construct(private JobApplicationReadModel $readModel)
    {
    }

    public function __invoke(FindJobApplicationQuery $query): JobApplicationDetails
    {
        $id = JobApplicationId::fromString($query->id);

        return $this->readModel->find($id) ?? throw JobApplicationNotFound::withId($id);
    }
}
