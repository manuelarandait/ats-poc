<?php

declare(strict_types=1);

namespace App\Recruitment\Application\ListJobOffers;

interface JobOfferReadModel
{
    /**
     * @return list<JobOfferView> ordered by title
     */
    public function all(): array;
}
