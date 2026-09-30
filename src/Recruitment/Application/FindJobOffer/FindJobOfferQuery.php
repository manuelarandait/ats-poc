<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobOffer;

use App\Recruitment\Application\ListJobOffers\JobOfferView;
use App\Shared\Domain\Bus\Query\Query;

/**
 * @implements Query<JobOfferView>
 */
final readonly class FindJobOfferQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
