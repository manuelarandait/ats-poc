<?php

declare(strict_types=1);

namespace App\Recruitment\Application;

use App\Recruitment\Application\FindJobApplication\JobApplicationDetails;
use App\Recruitment\Application\FindJobApplicationStats\JobApplicationStats;
use App\Recruitment\Application\SearchJobApplications\JobApplicationPage;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSearchCriteria;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobOffer\JobOfferId;

/**
 * Read-side port (CQRS): answers the screens' questions with flat DTOs,
 * without loading aggregates. Implemented with plain SQL in infrastructure.
 */
interface JobApplicationReadModel
{
    /**
     * Newest first (appliedAt desc), filtered and paginated.
     */
    public function search(JobApplicationSearchCriteria $criteria): JobApplicationPage;

    public function find(JobApplicationId $id): ?JobApplicationDetails;

    /**
     * Counts and average score over the applications matching the filters.
     */
    public function stats(?JobOfferId $jobOfferId, ?string $search): JobApplicationStats;
}
