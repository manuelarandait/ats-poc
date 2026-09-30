<?php

declare(strict_types=1);

namespace App\Recruitment\Application\ListJobOffers;

use App\Shared\Domain\Bus\Query\QueryHandler;

final readonly class ListJobOffersHandler implements QueryHandler
{
    public function __construct(private JobOfferReadModel $readModel)
    {
    }

    /**
     * @return list<JobOfferView>
     */
    public function __invoke(ListJobOffersQuery $query): array
    {
        return $this->readModel->all();
    }
}
