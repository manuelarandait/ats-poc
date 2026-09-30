<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobOffer;

use App\Recruitment\Application\ListJobOffers\JobOfferReadModel;
use App\Recruitment\Application\ListJobOffers\JobOfferView;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Recruitment\Domain\JobOffer\JobOfferNotFound;
use App\Shared\Domain\Bus\Query\QueryHandler;

final readonly class FindJobOfferHandler implements QueryHandler
{
    public function __construct(private JobOfferReadModel $readModel)
    {
    }

    public function __invoke(FindJobOfferQuery $query): JobOfferView
    {
        $id = JobOfferId::fromString($query->id);

        return $this->readModel->find($id) ?? throw JobOfferNotFound::withId($id);
    }
}
