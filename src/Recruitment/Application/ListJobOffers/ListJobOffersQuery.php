<?php

declare(strict_types=1);

namespace App\Recruitment\Application\ListJobOffers;

use App\Shared\Domain\Bus\Query\Query;

/**
 * @implements Query<list<JobOfferView>>
 */
final readonly class ListJobOffersQuery implements Query
{
}
