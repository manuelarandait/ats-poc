<?php

declare(strict_types=1);

namespace App\Recruitment\Application\ListJobOffers;

use App\Recruitment\Domain\JobOffer\JobOfferId;

interface JobOfferReadModel
{
    /**
     * @return list<JobOfferView> ordered by title
     */
    public function all(): array;

    public function find(JobOfferId $id): ?JobOfferView;
}
