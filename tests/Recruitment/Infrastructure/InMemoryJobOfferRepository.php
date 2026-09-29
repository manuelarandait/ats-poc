<?php

declare(strict_types=1);

namespace App\Tests\Recruitment\Infrastructure;

use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Recruitment\Domain\JobOffer\JobOfferRepository;

final class InMemoryJobOfferRepository implements JobOfferRepository
{
    /** @var array<string, JobOffer> */
    private array $offers = [];

    public function save(JobOffer $offer): void
    {
        $this->offers[$offer->id->value] = $offer;
    }

    public function find(JobOfferId $id): ?JobOffer
    {
        return $this->offers[$id->value] ?? null;
    }
}
