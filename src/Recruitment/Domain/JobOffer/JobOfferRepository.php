<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobOffer;

interface JobOfferRepository
{
    public function save(JobOffer $offer): void;

    public function find(JobOfferId $id): ?JobOffer;
}
